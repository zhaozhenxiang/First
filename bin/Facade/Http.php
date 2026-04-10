<?php

declare(strict_types=1);

namespace Bin\Facade;

use Bin\Http\HttpClient;
use Bin\Http\PendingRequest;

/**
 * HTTP Facade - 静态代理 HTTP 客户端
 *
 * @method static PendingRequest baseUrl(string $url)
 * @method static PendingRequest withHeaders(array $headers)
 * @method static PendingRequest withToken(string $token, string $type = 'Bearer')
 * @method static PendingRequest withBasicAuth(string $username, string $password)
 * @method static PendingRequest withCookies(array $cookies)
 * @method static PendingRequest withOptions(array $options)
 * @method static PendingRequest timeout(int $seconds)
 * @method static PendingRequest connectTimeout(int $seconds)
 * @method static PendingRequest withoutRedirecting()
 * @method static PendingRequest withoutVerifying()
 * @method static PendingRequest retry(int $times, int $sleepMs = 0, ?\Closure $when = null)
 * @method static PendingRequest beforeRequest(\Closure $callback)
 * @method static PendingRequest afterResponse(\Closure $callback)
 * @method static PendingRequest asJson()
 * @method static PendingRequest asForm()
 * @method static \Bin\Http\HttpResponse get(string $url, array $query = [])
 * @method static \Bin\Http\HttpResponse post(string $url, mixed $data = [])
 * @method static \Bin\Http\HttpResponse put(string $url, mixed $data = [])
 * @method static \Bin\Http\HttpResponse patch(string $url, mixed $data = [])
 * @method static \Bin\Http\HttpResponse delete(string $url, mixed $data = [])
 * @method static \Bin\Http\HttpResponse head(string $url, array $query = [])
 * @method static \Bin\Http\HttpResponse options(string $url, array $query = [])
 * @method static \Bin\Http\HttpResponse send(string $method, string $url, array $options = [])
 */
class Http extends Facade
{
    protected function getClassName(): string
    {
        return HttpClient::class;
    }

    protected static function getInstance(): object
    {
        return HttpClient::getInstance();
    }
}
