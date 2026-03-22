<?php

declare(strict_types=1);

namespace Bin\Session;

use RuntimeException;
use \SessionHandlerInterface;

/**
 * 文件 Session 处理器
 */
class FileSessionHandler implements \SessionHandlerInterface
{
    /** @var string Session 文件存储目录 */
    private string $path;

    /** @var int Session 文件生命周期（秒） */
    private int $lifetime;

    /**
     * 构造函数
     */
    public function __construct(?string $path = null, int $minutes = 120)
    {
        $this->path = $path ?? BASE_PATH . '/storage/sessions';
        $this->lifetime = $minutes * 60;

        // 确保目录存在
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * 打开 Session
     */
    public function open(string $path, string $name): bool
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }

        return true;
    }

    /**
     * 关闭 Session
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * 读取 Session 数据
     */
    public function read(string $id): string
    {
        $file = $this->getFilePath($id);

        if (!file_exists($file)) {
            return '';
        }

        // 检查文件是否过期
        if (filemtime($file) + $this->lifetime < time()) {
            @unlink($file);
            return '';
        }

        $content = file_get_contents($file);

        if ($content === false) {
            return '';
        }

        return $content;
    }

    /**
     * 写入 Session 数据
     */
    public function write(string $id, string $data): bool
    {
        $file = $this->getFilePath($id);

        $result = file_put_contents($file, $data, LOCK_EX);

        return $result !== false;
    }

    /**
     * 销毁 Session
     */
    public function destroy(string $id): bool
    {
        $file = $this->getFilePath($id);

        if (file_exists($file)) {
            return @unlink($file);
        }

        return true;
    }

    /**
     * 垃圾回收
     */
    public function gc(int $max_lifetime): int
    {
        $files = glob($this->path . '/*');
        $count = 0;
        $now = time();

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) + $max_lifetime < $now) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * 获取 Session 文件路径
     */
    private function getFilePath(string $id): string
    {
        return $this->path . '/' . 'sess_' . $id;
    }

    /**
     * 清空所有 Session 文件
     */
    public function clear(): bool
    {
        $files = glob($this->path . '/sess_*');

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        return true;
    }
}
