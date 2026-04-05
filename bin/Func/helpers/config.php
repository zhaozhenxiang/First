<?php

declare(strict_types=1);

if (!function_exists('config')) {
    /**
     * 获取/设置配置值
     */
    function config(...$args): mixed
    {
        static $repository = null;

        if ($repository === null) {
            $repository = new \Bin\Config\ConfigRepository();
        }

        if (empty($args)) {
            return $repository;
        }

        if (is_array($args[0])) {
            foreach ($args[0] as $key => $value) {
                $repository->set($key, $value);
            }

            if (isset($args[1]) && $args[1] === true) {
                $repository->saveAll();
            }

            return null;
        }

        if (count($args) >= 2 && $args[1] !== null) {
            $key = $args[0];
            $value = $args[1];
            $save = $args[2] ?? false;

            $repository->set($key, $value);

            if ($save) {
                [$file] = explode('.', $key);
                $repository->save($file);
            }

            return null;
        }

        $key = $args[0];
        $default = $args[1] ?? null;

        return $repository->get($key, $default);
    }
}

if (!function_exists('env')) {
    /**
     * 获取环境变量
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value
        };
    }
}
