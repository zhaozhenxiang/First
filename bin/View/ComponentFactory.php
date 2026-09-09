<?php

declare(strict_types=1);

namespace Bin\View;

/**
 * 组件运行时工厂
 *
 * 编译产物中的调用序列：
 *   ComponentFactory::startComponent('<编译后路径>', $data); ?>
 *      ... 默认插槽内容（可含 @slot('name') ... @endslot）...
 *   <?php echo ComponentFactory::renderComponent();
 *
 * renderComponent() 弹出栈顶组件，默认插槽为缓冲区剩余内容，
 * 组件视图内可用变量：$slot、$slots、$attributes 以及组件数据。
 */
class ComponentFactory
{
    /**
     * @var array<int, array{path: string, data: array<string, mixed>, slots: array<string, string>, anonymous: bool}>
     */
    private static array $components = [];

    /** @var array<int, string> 命名插槽名栈 */
    private static array $slotStack = [];

    /**
     * 开始组件渲染（开启输出缓冲收集默认插槽）
     *
     * @param array<string, mixed> $data
     */
    public static function startComponent(string $compiledPath, array $data = [], bool $anonymous = false): void
    {
        self::$components[] = [
            'path' => $compiledPath,
            'data' => $data,
            'slots' => [],
            'anonymous' => $anonymous,
        ];

        ob_start();
    }

    /**
     * 开始捕获命名插槽
     */
    public static function slot(string $name): void
    {
        if (self::$components === []) {
            return;
        }

        self::$slotStack[] = $name;
        ob_start();
    }

    /**
     * 结束命名插槽捕获
     */
    public static function endSlot(): void
    {
        $name = array_pop(self::$slotStack);

        if ($name === null || self::$components === []) {
            return;
        }

        self::$components[count(self::$components) - 1]['slots'][$name] = trim((string) ob_get_clean());
    }

    /**
     * 渲染栈顶组件并返回输出
     *
     * 组件视图内的变量优先级：
     * 组件数据 < 匿名组件属性变量 < $slot/$slots/$attributes
     */
    public static function renderComponent(): string
    {
        $component = array_pop(self::$components);

        if ($component === null) {
            return '';
        }

        $defaultSlot = trim((string) ob_get_clean());
        $namedSlots = $component['slots'];
        $componentData = $component['data'];
        $path = $component['path'];
        $attributeBag = new ComponentAttributeBag($component['anonymous'] ? $componentData : []);

        ob_start();

        try {
            extract($componentData, EXTR_SKIP);

            if ($component['anonymous']) {
                foreach ($componentData as $key => $value) {
                    $var = str_replace('-', '_', (string) $key);

                    $reserved = ['slot', 'slots', 'attributes', 'componentData', 'path'];
                    if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $var) === 1 && !in_array($var, $reserved, true)) {
                        $$var = $value;
                    }
                }
            }

            $slot = $defaultSlot;
            $slots = $namedSlots;
            $attributes = $attributeBag;

            require $path;

            return (string) ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();

            throw new \RuntimeException(
                'Component render error [' . $path . ']: ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    /**
     * 重置状态（用于测试）
     */
    public static function reset(): void
    {
        self::$components = [];
        self::$slotStack = [];
    }
}
