<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;

/**
 * make 命令抽象基类
 *
 * 封装公共逻辑：名称验证、目录创建、Stub 渲染、文件写入。
 * 子类只需定义 getTargetPath(), getStubFile(), getReplacements()。
 */
abstract class MakeCommand extends Command
{
    /**
     * 目标文件绝对路径
     */
    abstract protected function getTargetPath(string $name): string;

    /**
     * Stub 文件名（不含目录）
     */
    abstract protected function getStubFile(): string;

    /**
     * Stub 占位符替换映射
     *
     * @return array<string, string>
     */
    protected function getReplacements(string $name): array
    {
        return [
            '{{ class }}' => $this->className($name),
            '{{ namespace }}' => $this->resolveNamespace($name),
        ];
    }

    public function execute(): int
    {
        $name = $this->argument('name');

        if (empty($name)) {
            $this->error('Name is required.');
            return 1;
        }

        $this->validateName($name);

        $targetPath = $this->getTargetPath($name);

        // 确保目录存在
        $directory = dirname($targetPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // 检查文件是否已存在
        if (file_exists($targetPath)) {
            $this->error("File already exists: {$targetPath}");
            return 1;
        }

        // 渲染并写入
        $content = $this->renderStub($this->getStubFile(), $this->getReplacements($name));
        file_put_contents($targetPath, $content);

        $this->success("Created successfully: {$targetPath}");
        $this->afterCreate($name, $targetPath);

        return 0;
    }

    /**
     * 创建后的钩子（子类可选覆盖）
     */
    protected function afterCreate(string $name, string $path): void
    {
    }

    /**
     * 验证名称格式（PascalCase，支持路径分隔）
     */
    protected function validateName(string $name): void
    {
        $parts = explode('/', $name);
        foreach ($parts as $part) {
            if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $part)) {
                $this->error("Invalid name '{$part}'. Use PascalCase (e.g. PostController).");
                exit(1);
            }
        }
    }

    /**
     * 渲染 Stub 模板
     */
    protected function renderStub(string $stubFile, array $replacements): string
    {
        $stubPath = $this->resolveStubPath($stubFile);

        if (!file_exists($stubPath)) {
            throw new \RuntimeException("Stub not found: {$stubPath}");
        }

        $content = file_get_contents($stubPath);

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * 解析 Stub 路径：项目 stubs/ 优先，否则框架默认
     */
    protected function resolveStubPath(string $stub): string
    {
        // 项目级自定义 stub
        $customStub = basePath('stubs/' . $stub);
        if (file_exists($customStub)) {
            return $customStub;
        }

        // 框架默认 stub
        return dirname(__DIR__) . '/Stubs/' . $stub;
    }

    /**
     * 从路径名提取类名
     */
    protected function className(string $name): string
    {
        $parts = explode('/', $name);
        return end($parts);
    }

    /**
     * 解析命名空间（从目录结构推导）
     */
    protected function resolveNamespace(string $name): string
    {
        $parts = explode('/', $name);
        if (count($parts) > 1) {
            array_pop($parts);
            return $this->getBaseNamespace() . '\\' . implode('\\', $parts);
        }
        return $this->getBaseNamespace();
    }

    /**
     * 基础命名空间（子类可覆盖）
     */
    protected function getBaseNamespace(): string
    {
        return 'App';
    }

    /**
     * 获取基础目录（子类可覆盖）
     */
    protected function getBaseDirectory(): string
    {
        return basePath('app');
    }
}
