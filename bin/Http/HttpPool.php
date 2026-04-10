<?php

declare(strict_types=1);

namespace Bin\Http;

use CurlMultiHandle;
use RuntimeException;

/**
 * HTTP 并发请求池
 *
 * 使用 curl_multi 同时发送多个请求。
 *
 * 用法：
 *   $responses = HttpPool::pool(function ($pool) {
 *       return [
 *           $pool->as('users')->get('https://api.example.com/users'),
 *           $pool->as('posts')->get('https://api.example.com/posts'),
 *       ];
 *   });
 */
class HttpPool
{
    /** @var array<string, CurlHandle> 已标记的请求 */
    protected array $labeledHandles = [];

    /** @var array<int, string> handle ID → label 映射 */
    protected array $handleLabels = [];

    /** @var string|null 当前标签 */
    protected ?string $currentLabel = null;

    /**
     * 标记请求
     */
    public function as(string $key): static
    {
        $this->currentLabel = $key;
        return $this;
    }

    /**
     * 代理 HTTP 方法到 PendingRequest，收集 curl 句柄
     */
    public function __call(string $method, array $arguments): static
    {
        $request = new PendingRequest();

        if (!method_exists($request, $method)) {
            throw new RuntimeException("Method {$method} does not exist on PendingRequest");
        }

        // 调用 PendingRequest 的方法获取 HttpResponse
        // 但我们实际上需要构建 curl handle 而不是执行
        // 对于 Pool 模式，我们存储配置稍后执行
        $label = $this->currentLabel ?? (string) count($this->labeledHandles);
        $this->currentLabel = null;

        // 构建 curl handle
        $verb = strtoupper($method);
        $url = $arguments[0] ?? '';
        $data = $arguments[1] ?? [];

        $options = [];
        if (in_array($verb, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $options['query'] = is_array($data) ? $data : [];
        } else {
            $options['data'] = $data;
        }

        $ch = $request->buildCurlHandle($verb, $url, $options);

        $this->labeledHandles[$label] = $ch;
        $this->handleLabels[(int) $ch] = $label;

        return $this;
    }

    /**
     * 执行并发请求
     *
     * @param callable $callback 接收 $pool 实例，返回请求数组
     * @return array<string, HttpResponse>
     */
    public static function pool(callable $callback): array
    {
        $pool = new static();

        // 收集请求（回调中调用 $pool->as('x')->get(...) 等）
        $callback($pool);

        return $pool->execute();
    }

    /**
     * 执行所有并发请求
     *
     * @return array<string, HttpResponse>
     */
    public function execute(): array
    {
        if ($this->labeledHandles === []) {
            return [];
        }

        $mh = curl_multi_init();

        // 添加所有句柄
        $handles = [];
        foreach ($this->labeledHandles as $label => $ch) {
            curl_multi_add_handle($mh, $ch);
            $handles[$label] = $ch;
        }

        // 执行
        $active = null;
        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh, 1.0);
            }
        } while ($active && $status === CURLM_OK);

        // 收集响应
        $responses = [];
        foreach ($handles as $label => $ch) {
            $rawResponse = (string) curl_multi_getcontent($ch);

            if (curl_errno($ch) !== 0) {
                $responses[$label] = new HttpResponse(0, curl_error($ch));
            } else {
                $responses[$label] = HttpResponse::fromCurl($ch, $rawResponse);
            }

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);

        return $responses;
    }
}
