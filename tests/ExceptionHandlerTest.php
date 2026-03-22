<?php

declare(strict_types=1);

namespace Tests;

use Bin\Exception\AuthorizationException;
use Bin\Exception\AuthenticationException;
use Bin\Exception\ExceptionHandler;
use Bin\Exception\HttpException;
use Bin\Exception\NotFoundHttpException;
use Bin\Exception\ValidationException;
use Bin\Response\Response;
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
}
