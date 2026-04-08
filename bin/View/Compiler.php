<?php

declare(strict_types=1);

namespace Bin\View;

class Compiler
{
    private View $view;

    public function __construct(View $view)
    {
        $this->view = $view;
    }

    /**
     * @deprecated 使用 render() 代替
     */
    public function getPHP(): string
    {
        return $this->render();
    }

    /**
     * 渲染视图并返回输出字符串
     */
    public function render(): string
    {
        $__path = $this->view->getView();
        $__data = $this->view->getData();

        if (!file_exists($__path)) {
            throw new \RuntimeException("View file not found: {$__path}");
        }

        extract($__data);

        ob_start();

        try {
            require $__path;

            return ob_get_clean() ?: '';
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new \RuntimeException('View render error: ' . $e->getMessage(), 0, $e);
        }
    }
}