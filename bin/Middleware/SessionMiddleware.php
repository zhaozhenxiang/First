<?php

declare(strict_types=1);

namespace Bin\Middleware;

use Bin\App\App;
use Bin\Cookie\CookieManager;
use Bin\Request\Request;
use Bin\Response\ResponseFactory;
use Bin\Session\SessionManager;
use Closure;

class SessionMiddleware extends Middleware
{
    public function handle(mixed $request, Closure $next): mixed
    {
        if (!$request instanceof Request) {
            return $next($request);
        }

        $app = App::getInstance();
        /** @var SessionManager $session */
        $session = $app->make(SessionManager::class);

        $incomingId = $request->cookie($session->getName());
        if (is_string($incomingId) && $incomingId !== '' && !$session->isStarted()) {
            $session->setId($incomingId);
        }

        $session->start();

        $payload = $next($request);
        /** @var ResponseFactory $factory */
        $factory = $app->make(ResponseFactory::class);
        $response = $factory->make($payload);

        $this->syncCookieDefaults();
        $this->queueSessionCookie($session);
        $session->save();

        foreach (CookieManager::drainQueue() as $headerLine) {
            $response->appendHeader('Set-Cookie', $headerLine);
        }

        return $response;
    }

    private function syncCookieDefaults(): void
    {
        $config = (array) config('session.cookie', []);

        CookieManager::setDefaults([
            'path' => $config['path'] ?? '/',
            'domain' => $config['domain'] ?? '',
            'secure' => (bool) ($config['secure'] ?? false),
            'http_only' => (bool) ($config['http_only'] ?? true),
            'same_site' => $config['same_site'] ?? 'Lax',
        ]);
    }

    private function queueSessionCookie(SessionManager $session): void
    {
        if ($session->shouldExpireCookieOnResponse()) {
            CookieManager::forget($session->getName());
            $session->clearCookieExpirationFlag();
            return;
        }

        $minutes = (int) ceil($session->getLifetime() / 60);

        CookieManager::set($session->getName(), $session->getId(), $minutes);
    }
}
