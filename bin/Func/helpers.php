<?php

declare(strict_types=1);

use Bin\App\App;
use Bin\Log\LogManager;
use Bin\Validation\Validator;

if (!function_exists('getUrl')) {
    /**
     * 获取请求 URI
     */
    function getUrl(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }
}

if (!function_exists('getMethod')) {
    /**
     * 获取请求方法
     */
    function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
}

if (!function_exists('basePath')) {
    /**
     * 获取项目根路径
     */
    function basePath(string $path = ''): string
    {
        return BASE_PATH . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }
}

if (!function_exists('config')) {
    /**
     * 获取配置值
     */
    function config(string $key, mixed $default = null): mixed
    {
        $layered = explode(':', $key);

        if (empty($layered)) {
            return $default;
        }

        $configFile = basePath() . '/config/' . $layered[0] . '.php';

        if (!is_file($configFile)) {
            return $default;
        }

        $config = include $configFile;
        $value = $config;

        // 从1开始遍历
        for ($i = 1, $count = count($layered); $i < $count; $i++) {
            if (!isset($value[$layered[$i]])) {
                return $default;
            }
            $value = $value[$layered[$i]];
        }

        return $value;
    }
}

if (!function_exists('app')) {
    /**
     * 从 IoC 容器解析实例
     */
    function app(string $class): object
    {
        return App::make($class);
    }
}

if (!function_exists('abort')) {
    /**
     * 中止请求并返回错误码
     */
    function abort(int $code, string $message = ''): never
    {
        http_response_code($code);
        if ($message !== '') {
            echo $message;
        } else {
            echo $code;
        }
        exit;
    }
}

if (!function_exists('response')) {
    /**
     * 创建响应
     */
    function response(mixed $data = '', int $status = 200): \Bin\Response\Response
    {
        $response = new \Bin\Response\Response($data);
        $response->setStatus($status);
        return $response;
    }
}

if (!function_exists('redirect')) {
    /**
     * 重定向到指定 URL
     */
    function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header("Location: {$url}");
        exit;
    }
}

if (!function_exists('back')) {
    /**
     * 返回上一页
     */
    function back(): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        redirect($referer);
    }
}

if (!function_exists('old')) {
    /**
     * 获取旧的输入值（用于表单重新填充）
     */
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old_input'][$key] ?? $default;
    }
}

if (!function_exists('session')) {
    /**
     * 获取/设置 Session 值
     */
    function session(string $key, mixed $default = null): mixed
    {
        if (func_num_args() === 2) {
            return $_SESSION[$key] ?? $default;
        }

        $_SESSION[$key] = $default;
        return null;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * 获取 CSRF token
     */
    function csrf_token(): string
    {
        return \Bin\Middleware\CsrfMiddleware::generateToken();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * 生成 CSRF 隐藏字段
     */
    function csrf_field(): string
    {
        return \Bin\Middleware\CsrfMiddleware::field();
    }
}

if (!function_exists('logger')) {
    /**
     * 记录日志
     */
    function logger(string $channel = null): \Bin\Log\Logger
    {
        return LogManager::channel($channel);
    }
}

if (!function_exists('info')) {
    /**
     * 记录 info 日志
     */
    function info(string $message, array $context = []): void
    {
        LogManager::channel()->info($message, $context);
    }
}

if (!function_exists('error')) {
    /**
     * 记录 error 日志
     */
    function error(string $message, array $context = []): void
    {
        LogManager::channel()->error($message, $context);
    }
}

if (!function_exists('validate')) {
    /**
     * 验证输入
     */
    function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;

            foreach (explode('|', $rule) as $r) {
                $r = trim($r);

                // required
                if ($r === 'required' && $value === null) {
                    $errors[$field] = "{$field} is required";
                    break;
                }

                // email
                if ($r === 'email' && $value !== null) {
                    try {
                        Validator::email($value);
                    } catch (\InvalidArgumentException $e) {
                        $errors[$field] = "{$field} must be a valid email";
                        break;
                    }
                }

                // min:n
                if (str_starts_with($r, 'min:')) {
                    $min = (int) substr($r, 4);
                    if (is_string($value) && strlen($value) < $min) {
                        $errors[$field] = "{$field} must be at least {$min} characters";
                        break;
                    }
                }

                // max:n
                if (str_starts_with($r, 'max:')) {
                    $max = (int) substr($r, 4);
                    if (is_string($value) && strlen($value) > $max) {
                        $errors[$field] = "{$field} must not exceed {$max} characters";
                        break;
                    }
                }
            }
        }

        if ($errors !== []) {
            throw new \Bin\Exception\ValidationException($errors);
        }

        return $data;
    }
}

if (!function_exists('escape')) {
    /**
     * 转义 HTML 特殊字符
     */
    function escape(string $value): string
    {
        return Validator::escape($value);
    }
}

if (!function_exists('now')) {
    /**
     * 获取当前时间
     */
    function now(): \DateTime
    {
        return new \DateTime();
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
