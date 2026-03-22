<?php

declare(strict_types=1);

namespace Bin\Http;

/**
 * 上传文件处理
 */
class UploadedFile
{
    private array $file;
    private bool $test = false;
    private string $targetPath;

    /**
     * 构造函数
     */
    public function __construct(array $file, bool $test = false)
    {
        $this->file = $file;
        $this->test = $test;
    }

    /**
     * 从 $_FILES 创建实例
     */
    public static function createFromGlobal(string $key): ?self
    {
        if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return new self($_FILES[$key]);
    }

    /**
     * 从数组创建实例（支持测试模式）
     */
    public static function createFromArray(array $file, bool $test = false): self
    {
        return new self($file, $test);
    }

    /**
     * 获取客户端原始文件名
     */
    public function getClientOriginalName(): string
    {
        return $this->file['name'] ?? '';
    }

    /**
     * 获取客户端原始扩展名
     */
    public function getClientOriginalExtension(): string
    {
        return pathinfo($this->getClientOriginalName(), PATHINFO_EXTENSION);
    }

    /**
     * 获取 MIME 类型
     */
    public function getMimeType(): string
    {
        return $this->file['type'] ?? '';
    }

    /**
     * 获取文件大小（字节）
     */
    public function getSize(): int
    {
        return $this->file['size'] ?? 0;
    }

    /**
     * 获取错误代码
     */
    public function getError(): int
    {
        return $this->file['error'] ?? UPLOAD_ERR_NO_FILE;
    }

    /**
     * 检查是否有效
     */
    public function isValid(): bool
    {
        return $this->getError() === UPLOAD_ERR_OK;
    }

    /**
     * 获取错误消息
     */
    public function getErrorMessage(): string
    {
        return match ($this->getError()) {
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
            default => 'Unknown upload error',
        };
    }

    /**
     * 移动文件到指定位置
     */
    public function move(string $directory, ?string $name = null): string
    {
        if (!$this->isValid()) {
            throw new \RuntimeException($this->getErrorMessage());
        }

        $name = $name ?? $this->generateUniqueName();
        $targetPath = rtrim($directory, '/') . '/' . $name;

        // 确保目录存在
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if ($this->test) {
            return $targetPath;
        }

        if (!move_uploaded_file($this->file['tmp_name'], $targetPath)) {
            throw new \RuntimeException("Failed to move uploaded file to: {$targetPath}");
        }

        $this->targetPath = $targetPath;

        return $targetPath;
    }

    /**
     * 存储文件
     */
    public function store(string $directory, ?string $name = null): string
    {
        return $this->move($directory, $name);
    }

    /**
     * 存储为指定名称
     */
    public function storeAs(string $directory, string $name): string
    {
        return $this->move($directory, $name);
    }

    /**
     * 获取文件内容
     */
    public function get(): string|false
    {
        if (!$this->isValid()) {
            return false;
        }

        return file_get_contents($this->file['tmp_name']);
    }

    /**
     * 获取文件流
     */
    public function stream()
    {
        if (!$this->isValid()) {
            return false;
        }

        return fopen($this->file['tmp_name'], 'r');
    }

    /**
     * 验证文件类型
     */
    public function isValidMimeType(array $allowedTypes): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        return in_array($this->getMimeType(), $allowedTypes, true);
    }

    /**
     * 验证文件扩展名
     */
    public function isValidExtension(array $allowedExtensions): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        $extension = strtolower($this->getClientOriginalExtension());

        return in_array($extension, $allowedExtensions, true);
    }

    /**
     * 验证文件大小
     */
    public function isValidSize(int $maxSize): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        return $this->getSize() <= $maxSize;
    }

    /**
     * 验证文件
     */
    public function validate(array $rules = []): array
    {
        $errors = [];

        if (!$this->isValid()) {
            $errors['file'] = $this->getErrorMessage();
            return $errors;
        }

        if (isset($rules['max_size']) && !$this->isValidSize($rules['max_size'])) {
            $errors['max_size'] = "File size exceeds maximum allowed size of {$rules['max_size']} bytes";
        }

        if (isset($rules['allowed_types']) && !$this->isValidMimeType($rules['allowed_types'])) {
            $errors['mime_type'] = "File type {$this->getMimeType()} is not allowed";
        }

        if (isset($rules['allowed_extensions']) && !$this->isValidExtension($rules['allowed_extensions'])) {
            $errors['extension'] = "File extension {$this->getClientOriginalExtension()} is not allowed";
        }

        return $errors;
    }

    /**
     * 获取临时路径
     */
    public function getRealPath(): string
    {
        return $this->file['tmp_name'] ?? '';
    }

    /**
     * 获取目标路径（如果已移动）
     */
    public function getTargetPath(): ?string
    {
        return $this->targetPath ?? null;
    }

    /**
     * 生成唯一文件名
     */
    protected function generateUniqueName(): string
    {
        $extension = $this->getClientOriginalExtension();
        $name = md5(uniqid((string) random_int(1, PHP_INT_MAX), true));

        return $extension ? "{$name}.{$extension}" : $name;
    }

    /**
     * 哈希文件名
     */
    public function hashName(): string
    {
        return $this->generateUniqueName();
    }

    /**
     * 获取文件扩展名
     */
    public function extension(): string
    {
        return $this->getClientOriginalExtension();
    }

    /**
     * 假名（使用原始文件名）
     */
    public function storeAsOriginalName(string $directory): string
    {
        return $this->move($directory, $this->getClientOriginalName());
    }

    /**
     * 删除文件
     */
    public function delete(): bool
    {
        $path = $this->getTargetPath() ?? $this->getRealPath();

        if ($path && file_exists($path)) {
            return unlink($path);
        }

        return false;
    }

    /**
     * 检查文件是否存在
     */
    public function exists(): bool
    {
        $path = $this->getTargetPath() ?? $this->getRealPath();

        return $path && file_exists($path);
    }

    /**
     * 获取文件 URL
     */
    public function url(?string $baseUrl = null): string
    {
        if ($this->getTargetPath() === null) {
            return '';
        }

        $relativePath = str_replace(basePath(), '', $this->getTargetPath());
        $relativePath = str_replace('\\', '/', $relativePath);
        $relativePath = ltrim($relativePath, '/');

        $baseUrl = $baseUrl ?? config('app.url', '');

        return rtrim($baseUrl, '/') . '/' . $relativePath;
    }
}
