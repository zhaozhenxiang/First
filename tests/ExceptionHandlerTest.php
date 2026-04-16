<?php

declare(strict_types=1);

namespace Tests;

use Bin\App\App;
use Bin\Exception\AuthorizationException;
use Bin\Exception\AuthenticationException;
use Bin\Exception\ExceptionHandler;
use Bin\Exception\HttpException;
use Bin\Exception\NotFoundHttpException;
use Bin\Exception\ValidationException;
use Bin\Response\Response;
use Bin\Response\ResponseFactory;
use Bin\Testing\TestCase;

/**
 * 异常处理器测试
 */
class ExceptionHandlerTest extends TestCase
{
    private ExceptionHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new ExceptionHandler(true);
    }

    public function testGetStatusFromHttpException(): void
    {
        $e = new NotFoundHttpException();
        $status = $this->handler->render($e);

        $this->assertInstanceOf(Response::class, $status);
    }

    public function testGetStatusFromAuthenticationException(): void
    {
        $e = new AuthenticationException();
        $response = $this->handler->render($e);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testGetStatusFromAuthorizationException(): void
    {
        $e = new AuthorizationException();
        $response = $this->handler->render($e);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testGetStatusFromValidationException(): void
    {
        $e = new ValidationException(['email' => 'Invalid email']);
        $response = $this->handler->render($e);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testGetStatusFromGenericException(): void
    {
        $e = new \Exception('Something went wrong');
        $response = $this->handler->render($e);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testReportableCallback(): void
    {
        $reported = false;
        $exception = new \Exception('Test exception');

        $this->handler->reportable(\Exception::class, function (\Exception $e) use (&$reported, $exception) {
            if ($e === $exception) {
                $reported = true;
            }
        });

        $this->handler->report($exception);

        $this->assertTrue($reported);
    }

    public function testRenderableCallback(): void
    {
        $exception = new NotFoundHttpException();
        $customResponse = new Response('Custom not found', 404);

        $this->handler->renderable(NotFoundHttpException::class, function () use ($customResponse) {
            return $customResponse;
        });

        $response = $this->handler->render($exception);

        $this->assertSame($customResponse, $response);
    }

    public function testDontReport(): void
    {
        $reported = false;
        $exception = new NotFoundHttpException();

        $this->handler->reportable(\Exception::class, function () use (&$reported) {
            $reported = true;
        });

        $this->handler->report($exception);

        // NotFoundHttpException 默认在 dontReport 列表中
        $this->assertFalse($reported);
    }

    public function testSetDebug(): void
    {
        $this->assertTrue($this->handler->isDebug());

        $this->handler->setDebug(false);

        $this->assertFalse($this->handler->isDebug());
    }

    public function testDontReportArray(): void
    {
        $reported = false;
        $exception = new \Exception('Test');

        $this->handler->dontReport([\Exception::class]);
        $this->handler->reportable(\Exception::class, function () use (&$reported) {
            $reported = true;
        });

        $this->handler->report($exception);

        $this->assertFalse($reported);
    }

    public function testHttpException(): void
    {
        $e = new HttpException(418, "I'm a teapot");
        $response = $this->handler->render($e);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testValidationExceptionGetErrors(): void
    {
        $errors = [
            'email' => 'Invalid email format',
            'password' => 'Password is required',
        ];

        $e = new ValidationException($errors);

        $this->assertEquals($errors, $e->getErrors());
    }

    public function testValidationExceptionGetFirstError(): void
    {
        $errors = [
            'email' => 'Invalid email format',
            'password' => 'Password is required',
        ];

        $e = new ValidationException($errors);

        $this->assertEquals('Invalid email format', $e->getFirstError());
    }

    public function testValidationExceptionGetFirstErrorEmpty(): void
    {
        $e = new ValidationException([]);

        $this->assertEquals('Validation failed', $e->getFirstError());
    }

    public function testNotFoundHttpExceptionDefaultMessage(): void
    {
        $e = new NotFoundHttpException();

        $this->assertEquals('Resource not found', $e->getMessage());
    }

    public function testAuthenticationExceptionDefaultMessage(): void
    {
        $e = new AuthenticationException();

        $this->assertEquals('Unauthenticated', $e->getMessage());
    }

    public function testAuthorizationExceptionDefaultMessage(): void
    {
        $e = new AuthorizationException();

        $this->assertEquals('This action is unauthorized', $e->getMessage());
    }

    public function testHttpExceptionGetStatusCode(): void
    {
        $e = new HttpException(418, 'Test');

        $this->assertEquals(418, $e->getStatusCode());
    }

    public function testHttpExceptionGetHeaders(): void
    {
        $headers = ['X-Custom' => 'value'];
        $e = new HttpException(500, 'Test', null, $headers);

        $this->assertEquals($headers, $e->getHeaders());
    }

    public function testRenderWithDebug(): void
    {
        $this->handler->setDebug(true);
        $e = new \Exception('Debug test');

        $response = $this->handler->render($e);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRenderWithoutDebug(): void
    {
        $this->handler->setDebug(false);
        $e = new \Exception('Production error');

        $response = $this->handler->render($e);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRenderForConsoleIncludesExceptionMessage(): void
    {
        $output = $this->handler->renderForConsole(new \RuntimeException('console boom'));

        $this->assertStringContainsString('console boom', $output);
    }

    // ================================================================
    // 异常驱动流程 — 各模块 throw → ExceptionHandler render
    // ================================================================

    public function testAbortThrowsHttpException(): void
    {
        try {
            abort(403, 'Forbidden');
            $this->fail('Expected HttpException was not thrown');
        } catch (\Bin\Exception\HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertEquals('Forbidden', $e->getMessage());
        }
    }

    public function testAbortDefaultMessage(): void
    {
        try {
            abort(500);
        } catch (\Bin\Exception\HttpException $e) {
            $this->assertEquals(500, $e->getStatusCode());
            $this->assertEquals('Error 500', $e->getMessage());
        }
    }

    public function testAbortCustomMessage(): void
    {
        try {
            abort(403, 'Go away');
        } catch (\Bin\Exception\HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertEquals('Go away', $e->getMessage());
        }
    }

    public function testRateLimitExceededExceptionRendered(): void
    {
        $e = new \Bin\Exception\RateLimitExceededException(
            'Too many requests',
            null,
            ['Retry-After' => '60']
        );

        $response = $this->handler->render($e);

        $this->assertInstanceOf(Response::class, $response);
        // debug 模式下返回包含异常信息的 HTML
        $this->assertStringContainsString('429', $response->getContent());
        $this->assertEquals('60', $e->getHeaders()['Retry-After']);
    }

    public function testMethodNotAllowedHttpExceptionRendered(): void
    {
        $e = new \Bin\Exception\MethodNotAllowedHttpException(
            'Method Not Allowed',
            ['GET', 'POST']
        );

        $response = $this->handler->render($e);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(['Allow' => 'GET, POST'], $e->getHeaders());
    }

    public function testRouteNotFoundThrowsNotFoundHttpException(): void
    {
        \Bin\Route\RouteCollection::clear();

        try {
            \Bin\Route\RouteCollection::getRoute();
            $this->fail('Expected NotFoundHttpException was not thrown');
        } catch (\Bin\Exception\NotFoundHttpException $e) {
            $this->assertEquals(404, $e->getStatusCode());
        }
    }

    public function testExceptionHandlerHandlesAllHttpCodes(): void
    {
        $codes = [401, 403, 404, 405, 422, 429, 500];
        foreach ($codes as $code) {
            $e = new \Bin\Exception\HttpException($code, "Error {$code}");
            $response = $this->handler->render($e);
            $this->assertInstanceOf(Response::class, $response, "Failed for HTTP {$code}");
        }
    }

    public function testValidationExceptionWebRedirect(): void
    {
        // 非 AJAX 的 ValidationException → 重定向响应
        $_SERVER['HTTP_REFERER'] = '/previous';
        $_POST = ['name' => 'bad'];

        $handler = new ExceptionHandler(false);
        $e = new ValidationException(['name' => 'Name is required']);

        $response = $handler->render($e);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/previous', $response->getHeader('Location'));
    }

    public function testAjaxRenderFallsBackToDirectResponseFactoryWhenContainerResolutionFails(): void
    {
        $app = App::getInstance();
        $app->forget(ResponseFactory::class);
        $app->singleton(ResponseFactory::class, function (): ResponseFactory {
            throw new \RuntimeException('factory broken');
        });
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        try {
            $response = $this->handler->render(new \RuntimeException('ajax boom'));

            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals('application/json', $response->getHeader('Content-Type'));
            $this->assertEquals(500, $response->getStatusCode());
        } finally {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
            $app->forget(ResponseFactory::class);
            $app->singleton(ResponseFactory::class, ResponseFactory::class);
        }
    }

    public function testGenericMessageMapping(): void
    {
        // 通过反射测试 getGenericMessage — 间接通过 render
        $handler = new ExceptionHandler(false);
        $e = new \Bin\Exception\HttpException(400, '');
        $response = $handler->render($e);
        $this->assertInstanceOf(Response::class, $response);

        $e = new \Bin\Exception\HttpException(503, '');
        $response = $handler->render($e);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testDontReportIncludesAuthenticationAndValidation(): void
    {
        $reported = [];

        $this->handler->reportable(\Throwable::class, function (\Throwable $e) use (&$reported) {
            $reported[] = get_class($e);
        });

        $this->handler->report(new AuthenticationException());
        $this->handler->report(new ValidationException([]));
        $this->handler->report(new NotFoundHttpException());

        // 这些异常不应被报告
        $this->assertNotContains(AuthenticationException::class, $reported);
        $this->assertNotContains(ValidationException::class, $reported);
        $this->assertNotContains(NotFoundHttpException::class, $reported);

        // 其他异常应该报告
        $this->handler->report(new \RuntimeException('test'));
        $this->assertContains('RuntimeException', $reported);
    }
}
