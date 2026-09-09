<?php

declare(strict_types=1);

namespace Bin\View\Blade;

/**
 * Blade 模板编译器
 *
 * 将 .blade.php 编译为纯 PHP 并缓存到 storage/views/。
 * 编译规则：
 *   {{ $var }}  → htmlspecialchars 转义输出
 *   {!! $var !!} → 原始输出
 *   @if/@elseif/@else/@endif → PHP 条件
 *   @foreach/@endforeach → PHP 循环
 *   @extends/@section/@yield → 模板继承
 *   @include → 子视图包含
 *   @stack/@push/@prepend → 栈系统
 *   @component/@slot/@endcomponent → 组件与插槽
 *   <x-name> → 匿名组件（views/components/name.blade.php）
 */
class BladeCompiler
{
    /** 编译缓存路径 */
    protected string $cachePath;

    /** 视图根路径 */
    protected string $viewPath;

    /** @var array<string, callable(string, string): string> 自定义指令编译器 */
    protected array $customCompilers = [];

    /** @var array<string, string> 栈内容 [stackName => content] */
    protected array $stacks = [];

    public function __construct(string $viewPath, string $cachePath)
    {
        $this->viewPath = rtrim($viewPath, '/');
        $this->cachePath = rtrim($cachePath, '/');

        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    /**
     * 编译 Blade 文件并返回缓存路径
     *
     * 如果缓存存在且未过期，直接返回缓存路径。
     * 否则重新编译并写入缓存。
     */
    public function compile(string $path): string
    {
        $fullPath = str_ends_with($path, '.blade.php')
            ? $this->viewPath . '/' . $path
            : $this->viewPath . '/' . $path . '.blade.php';

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Blade view not found: {$fullPath}");
        }

        $cachedPath = $this->getCachedPath($path);

        // 一次读取文件，同时获取内容和依赖
        [$content, $dependsOn] = $this->readWithDependencies($fullPath);

        // 缓存有效检查
        if ($this->isCacheValid($fullPath, $cachedPath) && $this->areDependenciesValid($dependsOn, $cachedPath)) {
            return $cachedPath;
        }

        // 编译（可能递归处理继承）
        $compiled = $this->compileWithInheritance($content, dirname($path));

        // 写入缓存
        file_put_contents($cachedPath, $compiled);

        return $cachedPath;
    }

    /**
     * 编译 Blade 字符串为 PHP
     */
    public function compileString(string $template): string
    {
        $result = $template;

        // 1. 注释 {{-- --}} → 空
        $result = preg_replace('/\{\{--(.*?)--\}\}/s', '', $result);

        // 2. 原始输出 {!! !!} → 未转义 echo
        $result = preg_replace_callback('/\{!!\s*(.+?)\s*!!\}/s', function ($matches) {
            return '<?php echo ' . $matches[1] . '; ?>';
        }, $result);

        // 3. 转义输出 {{ }} → htmlspecialchars
        $result = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/s', function ($matches) {
            return '<?php echo htmlspecialchars((string) (' . $matches[1] . '), ENT_QUOTES, \'UTF-8\'); ?>';
        }, $result);

        // 4. Blade 指令编译（顺序重要！）
        $result = $this->compileDirectives($result);

        return $result;
    }

    /**
     * 带继承的编译：处理 @extends/@section/@yield/@include
     */
    protected function compileWithInheritance(string $template, string $relativeDir): string
    {
        // 提取 @extends 指令
        $extends = null;
        if (preg_match('/@extends\s*\(\s*[\'"](.+?)[\'"]\s*\)/', $template, $matches)) {
            $extends = $matches[1];
            // 移除 @extends 行
            $template = preg_replace('/@extends\s*\(\s*[\'"].+?[\'"]\s*\)\s*\n?/', '', $template);
        }

        // 编译 @section/@endsection → 收集到 $__sections
        $template = $this->compileSections($template);

        // 编译 @include
        $template = $this->compileIncludes($template, $relativeDir);

        // 编译 @stack/@push/@prepend
        $template = $this->compileStacks($template);

        // 编译组件（@component 与 <x-*> 匿名组件）
        $template = $this->compileComponents($template, $relativeDir);

        // 编译其余 Blade 语法
        $template = $this->compileString($template);

        if ($extends !== null) {
            return $this->wrapExtends($extends, $template, $relativeDir);
        }

        return $template;
    }

    /**
     * 编译 @section/@endsection → 将内容存储到 $__sections 数组
     */
    protected function compileSections(string $template): string
    {
        // @section('name') ... @endsection → 存储
        $template = preg_replace_callback(
            '/@section\s*\(\s*[\'"](\w+)[\'"]\s*\)(.*?)@endsection/s',
            function ($matches) {
                $name = $matches[1];
                $content = $matches[2];
                return '<?php $__sections[\'' . $name . '\'] = function() use (&$__sections) { extract($__data ?? []); ?>'
                    . $content
                    . '<?php }; ?>';
            },
            $template
        );

        // @section('name', 'default') → 简写
        $template = preg_replace_callback(
            '/@section\s*\(\s*[\'"](\w+)[\'"]\s*,\s*[\'"](.+?)[\'"]\s*\)/s',
            function ($matches) {
                return '<?php $__sections[\'' . $matches[1] . '\'] = function() { ?>' . $matches[2] . '<?php }; ?>';
            },
            $template
        );

        // @yield('name') → 输出 section 内容
        $template = preg_replace_callback(
            '/@yield\s*\(\s*[\'"](\w+)[\'"]\s*(?:,\s*[\'"](.+?)[\'"])?\s*\)/s',
            function ($matches) {
                $name = $matches[1];
                $default = $matches[2] ?? '';
                return '<?php echo isset($__sections[\'' . $name . '\']) ? $__sections[\'' . $name . '\']() : \'' . $default . '\'; ?>';
            },
            $template
        );

        // @overwrite 标记（用于 @section 覆盖模式）
        // @parent → 追加到父 section
        $template = str_replace('@parent', '<?php echo isset($__parentSection) ? $__parentSection : \'\' ?>', $template);

        return $template;
    }

    /**
     * 编译 @include 指令
     */
    protected function compileIncludes(string $template, string $relativeDir): string
    {
        return preg_replace_callback(
            '/@include\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(\[\s*.*?\s*\]))?\s*\)/s',
            function ($matches) use ($relativeDir) {
                $view = $matches[1];
                $data = $matches[2] ?? '[]';

                // 解析视图路径
                $viewPath = $this->resolveViewPath($view, $relativeDir);

                return '<?php $__includeData = array_merge($__data ?? [], ' . $data . '); '
                    . 'extract($__includeData); '
                    . 'ob_start(); '
                    . 'require \'' . $viewPath . '\'; '
                    . 'echo ob_get_clean(); ?>';
            },
            $template
        );
    }

    /**
     * 编译组件指令
     *
     * - @component('name', [...]) ... @endcomponent
     * - <x-name attr="v" :bound="expr">...</x-name>（匿名组件，自闭合支持）
     */
    protected function compileComponents(string $template, string $relativeDir): string
    {
        // 1. @component ... @endcomponent（由内向外迭代编译，支持嵌套）
        $iterations = 0;
        do {
            $replaced = preg_replace_callback(
                '/@component\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*(\[\s*.*?\s*\]))?\s*\)((?:(?!@component\b).)*?)@endcomponent/s',
                function ($matches) use ($relativeDir): string {
                    $view = $matches[1];
                    $data = $matches[2] ?? '[]';
                    $content = $matches[3];

                    $path = addslashes($this->resolveViewPath($view, $relativeDir));

                    return '<?php \Bin\View\ComponentFactory::startComponent(\'' . $path . '\', ' . $data . '); ?>'
                        . $content
                        . '<?php echo \Bin\View\ComponentFactory::renderComponent(); ?>';
                },
                $template,
                1,
                $count
            );

            if ($replaced !== null) {
                $template = $replaced;
            }
            $iterations++;
        } while ($count > 0 && $iterations < 50);

        // 2. 自闭合匿名组件 <x-name ... />
        $template = preg_replace_callback(
            '/<x-([\w\-\.]+)((?:\s+[^\s>\/]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?)*)\/>/',
            function ($matches) use ($relativeDir): string {
                return $this->compileAnonymousTag($matches[1], $matches[2] ?? '', '', $relativeDir);
            },
            $template
        );

        // 3. 成对匿名组件 <x-name ...> ... </x-name>
        $template = preg_replace_callback(
            '/<x-([\w\-\.]+)((?:\s+[^\s>\/]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?)*)>(.*?)<\/x-\1>/s',
            function ($matches) use ($relativeDir): string {
                return $this->compileAnonymousTag($matches[1], $matches[2] ?? '', $matches[3] ?? '', $relativeDir);
            },
            $template
        );

        return $template;
    }

    /**
     * 编译单个匿名组件标签
     */
    protected function compileAnonymousTag(string $name, string $attributeString, string $content, string $relativeDir): string
    {
        $view = 'components.' . $name;
        $path = addslashes($this->resolveViewPath($view, $relativeDir));
        $dataExpression = $this->buildAttributeArray($attributeString);

        return '<?php \Bin\View\ComponentFactory::startComponent(\'' . $path . '\', ' . $dataExpression . ', true); ?>'
            . $content
            . '<?php echo \Bin\View\ComponentFactory::renderComponent(); ?>';
    }

    /**
     * 将标签属性字符串编译为 PHP 数组表达式
     *
     * 静态属性 type="error" → 'type' => 'error'
     * 绑定属性 :message="$msg" → 'message' => ($msg)
     * 裸属性 disabled → 'disabled' => true
     */
    protected function buildAttributeArray(string $attributeString): string
    {
        $parts = [];

        // key=value（静态或 :绑定）
        if (preg_match_all('/(:?)([\w\-]+)\s*=\s*("([^"]*)"|\'([^\']*)\')/', $attributeString, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $set) {
                $bound = $set[1] === ':';
                $key = $set[2];
                $value = $set[4] ?? $set[5] ?? '';

                $parts[] = var_export($key, true) . ' => ' . ($bound ? '(' . $value . ')' : var_export($value, true));
            }
        }

        // 裸属性（移除已匹配的 key=value 后剩余的单词）
        $residual = preg_replace('/:?[\w\-]+\s*=\s*("[^"]*"|\'[^\']*\')/', '', $attributeString) ?? '';
        if (preg_match_all('/[\w\-]+/', $residual, $bare) > 0) {
            foreach ($bare[0] as $key) {
                $parts[] = var_export($key, true) . ' => true';
            }
        }

        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * 编译栈指令 @stack/@push/@prepend
     */
    protected function compileStacks(string $template): string
    {
        // @push('name') ... @endpush
        $template = preg_replace_callback(
            '/@push\s*\(\s*[\'"](\w+)[\'"]\s*\)(.*?)@endpush/s',
            function ($matches) {
                return '<?php if(!isset($__stacks[\'' . $matches[1] . '\'])) $__stacks[\'' . $matches[1] . '\'] = \'\' ?>'
                    . '<?php ob_start(); ?>'
                    . $matches[2]
                    . '<?php $__stacks[\'' . $matches[1] . '\'] .= ob_get_clean(); ?>';
            },
            $template
        );

        // @prepend('name') ... @endprepend
        $template = preg_replace_callback(
            '/@prepend\s*\(\s*[\'"](\w+)[\'"]\s*\)(.*?)@endprepend/s',
            function ($matches) {
                return '<?php if(!isset($__stacks[\'' . $matches[1] . '\'])) $__stacks[\'' . $matches[1] . '\'] = \'\' ?>'
                    . '<?php ob_start(); ?>'
                    . $matches[2]
                    . '<?php $__stacks[\'' . $matches[1] . '\'] = ob_get_clean() . $__stacks[\'' . $matches[1] . '\']; ?>';
            },
            $template
        );

        // @stack('name') → 输出栈内容
        $template = preg_replace_callback(
            '/@stack\s*\(\s*[\'"](\w+)[\'"]\s*\)/',
            function ($matches) {
                return '<?php echo $__stacks[\'' . $matches[1] . '\'] ?? \'\' ?>';
            },
            $template
        );

        return $template;
    }

    /**
     * 包装子模板为"注册 sections + include 父模板"
     *
     * 编译产物结构：
     *   1. 初始化 $__sections, $__stacks
     *   2. 执行子模板（注册 sections，非 section 内容被忽略）
     *   3. Include 父模板的编译缓存（使用 $__sections）
     */
    protected function wrapExtends(string $parent, string $childCompiled, string $relativeDir): string
    {
        // 编译父模板，获取缓存路径
        $parentPath = $this->resolveViewPath($parent, $relativeDir);

        // 运行时：先执行子模板注册 sections，再 include 父模板
        return '<?php' . "\n"
            . '$__sections = $__sections ?? [];' . "\n"
            . '$__stacks = $__stacks ?? [];' . "\n"
            . '?>'
            // 子模板内容（包含 section 注册代码）
            . $childCompiled
            // Include 父模板
            . '<?php require \'' . addslashes($parentPath) . '\'; ?>';
    }

    /**
     * 解析视图路径
     */
    protected function resolveViewPath(string $view, string $relativeDir): string
    {
        // 绝对路径（以 / 或 . 开头视为相对 viewPath）
        $view = str_replace('.', '/', $view);

        $bladePath = $this->viewPath . '/' . $view . '.blade.php';
        $phpPath = $this->viewPath . '/' . $view . '.php';

        if (file_exists($bladePath)) {
            // 需要编译子视图
            $compiledPath = $this->compile($view . '.blade.php');
            return $compiledPath;
        }

        if (file_exists($phpPath)) {
            return $phpPath;
        }

        throw new \RuntimeException("Included view not found: {$view}");
    }

    /**
     * 读取模板文件并扫描 @extends 依赖（单次文件读取）
     *
     * @return array{0: string, 1: array<string>} [content, dependencyPaths]
     */
    protected function readWithDependencies(string $fullPath): array
    {
        $content = file_get_contents($fullPath);
        $depends = [$fullPath];

        if (preg_match('/@extends\s*\(\s*[\'"](.+?)[\'"]\s*\)/', $content, $matches)) {
            $parent = str_replace('.', '/', $matches[1]);
            $parentBlade = $this->viewPath . '/' . $parent . '.blade.php';
            if (file_exists($parentBlade)) {
                [, $parentDeps] = $this->readWithDependencies($parentBlade);
                $depends = array_merge($depends, $parentDeps);
            }
        }

        // 组件依赖：@component('name') 与 <x-name>（name 中点号映射为目录分隔）
        $componentViews = [];

        if (preg_match_all('/@component\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches) > 0) {
            $componentViews = array_merge($componentViews, $matches[1]);
        }

        if (preg_match_all('/<x-([\w\-\.]+)/', $content, $matches) > 0) {
            foreach ($matches[1] as $tag) {
                $componentViews[] = 'components.' . $tag;
            }
        }

        foreach ($componentViews as $view) {
            $blade = $this->viewPath . '/' . str_replace('.', '/', $view) . '.blade.php';
            if (file_exists($blade)) {
                [, $componentDeps] = $this->readWithDependencies($blade);
                $depends = array_merge($depends, $componentDeps);
            }
        }

        return [$content, array_values(array_unique($depends))];
    }

    /**
     * 检查所有依赖文件是否都比缓存旧
     */
    protected function areDependenciesValid(array $depends, string $cachedPath): bool
    {
        if (!file_exists($cachedPath)) {
            return false;
        }

        $cacheTime = filemtime($cachedPath);
        foreach ($depends as $dep) {
            if (file_exists($dep) && filemtime($dep) > $cacheTime) {
                return false;
            }
        }

        return true;
    }

    /**
     * 编译所有 Blade 指令
     */
    protected function compileDirectives(string $template): string
    {
        $directives = [
            // PHP 块
            '/@php\s*\((.*?)\)\s*$/'  => '<?php $1; ?>',
            '/@php\b/'                => '<?php',
            '/@endphp\b/'             => '?>',

            // 条件
            '/@if\s*\((.*?)\)/'       => '<?php if($1): ?>',
            '/@elseif\s*\((.*?)\)/'    => '<?php elseif($1): ?>',
            '/@else\b/'               => '<?php else: ?>',
            '/@endif\b/'              => '<?php endif; ?>',
            '/@isset\s*\((.*?)\)/'     => '<?php if(isset($1)): ?>',
            '/@endisset\b/'           => '<?php endif; ?>',
            '/@empty\s*\((.*?)\)/'     => '<?php if(empty($1)): ?>',
            '/@endempty\b/'           => '<?php endif; ?>',
            '/@unless\s*\((.*?)\)/'    => '<?php if(!($1)): ?>',
            '/@endunless\b/'          => '<?php endif; ?>',

            // 循环
            '/@foreach\s*\((.*?)\)/'   => '<?php foreach($1): ?>',
            '/@endforeach\b/'         => '<?php endforeach; ?>',
            '/@for\s*\((.*?)\)/'       => '<?php for($1): ?>',
            '/@endfor\b/'             => '<?php endfor; ?>',
            '/@while\s*\((.*?)\)/'     => '<?php while($1): ?>',
            '/@endwhile\b/'           => '<?php endwhile; ?>',

            // switch
            '/@switch\s*\((.*?)\)/'    => '<?php switch($1): ?>',
            '/@case\s*\((.*?)\)/'      => '<?php case $1: ?>',
            '/@default\b/'            => '<?php default: ?>',
            '/@break\b/'              => '<?php break; ?>',
            '/@endswitch\b/'          => '<?php endswitch; ?>',

            // 循环控制
            '/@continue\s*\((.*?)\)/'  => '<?php if($1) continue; ?>',
            '/@continue\b/'           => '<?php continue; ?>',
            '/@break\s*\((.*?)\)/'     => '<?php if($1) break; ?>',

            // 输出
            '/@echo\s*\((.*?)\)/'      => '<?php echo $1; ?>',
            '/@dump\s*\((.*?)\)/'      => '<?php var_dump($1); ?>',
            '/@dd\s*\((.*?)\)/'        => '<?php dd($1); ?>',

            // 认证（简化版）
            '/@auth\b/'               => '<?php if(\Bin\Auth\AuthManager::check()): ?>',
            '/@endauth\b/'            => '<?php endif; ?>',
            '/@guest\b/'              => '<?php if(!\Bin\Auth\AuthManager::check()): ?>',
            '/@endguest\b/'           => '<?php endif; ?>',

            // 错误（简化版）
            '/@error\s*\([\'"](.+?)[\'"]\)/' => '<?php if(isset($errors) && $errors->has(\'$1\')): ?>',
            '/@enderror\b/'                     => '<?php endif; ?>',

            // method / csrf
            '/@method\s*\([\'"](.+?)[\'"]\)/' => '<input type="hidden" name="_method" value="$1">',
            '/@csrf\b/'                        => '<?php echo \\Bin\\Middleware\\CsrfMiddleware::field(); ?>',

            // 组件插槽
            '/@slot\s*\(\s*[\'"](\w+)[\'"]\s*\)/' => '<?php \Bin\View\ComponentFactory::slot(\'$1\'); ?>',
            '/@endslot\b/'                          => '<?php \Bin\View\ComponentFactory::endSlot(); ?>',

            // 每循环变量
            '/\$loop\b/' => '$__loop',
        ];

        foreach ($directives as $pattern => $replacement) {
            $template = preg_replace($pattern, $replacement, $template);
        }

        // 自定义指令
        foreach ($this->customCompilers as $name => $compiler) {
            $pattern = '/@' . preg_quote($name, '/') . '(\s*\((.*?)\))?/';
            $template = preg_replace_callback($pattern, function ($matches) use ($compiler) {
                $expression = $matches[2] ?? '';
                return $compiler($expression, '');
            }, $template);
        }

        return $template;
    }

    /**
     * 注册自定义编译指令
     */
    public function directive(string $name, callable $compiler): static
    {
        $this->customCompilers[$name] = $compiler;

        return $this;
    }

    /**
     * 获取缓存文件路径
     */
    public function getCachedPath(string $path): string
    {
        $hash = md5($path);
        return $this->cachePath . '/' . $hash . '.php';
    }

    /**
     * 检查缓存是否有效
     */
    protected function isCacheValid(string $sourcePath, string $cachedPath): bool
    {
        if (!file_exists($cachedPath)) {
            return false;
        }

        return filemtime($sourcePath) <= filemtime($cachedPath);
    }

    /**
     * 清除所有编译缓存
     */
    public function clearCache(): static
    {
        $files = glob($this->cachePath . '/*.php');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }

        return $this;
    }

    /**
     * 获取视图路径
     */
    public function getViewPath(): string
    {
        return $this->viewPath;
    }

    /**
     * 获取缓存路径
     */
    public function getCachePath(): string
    {
        return $this->cachePath;
    }
}
