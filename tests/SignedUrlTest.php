<?php

declare(strict_types=1);

namespace Tests;

use Bin\Facade\URL;
use Bin\Request\Request;
use Bin\Route\RouteCollection as Route;
use Bin\Route\SignedUrl;
use Bin\Testing\TestCase;

class SignedUrlTest extends TestCase
{
    private mixed $previousAppKey = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousAppKey = config('app.key');
        config(['app.key' => 'testing-secret']);
        Route::clear();
    }

    protected function tearDown(): void
    {
        Route::clear();
        config(['app.key' => $this->previousAppKey]);

        parent::tearDown();
    }

    public function testSignedRouteGeneratesHmacForNamedRoute(): void
    {
        Route::get('/invites/{invite}', 'InviteController@show')->name('invites.show');

        $signature = hash_hmac(
            'sha256',
            '/invites/42?email=taylor%40example.com',
            'testing-secret'
        );

        $this->assertSame(
            '/invites/42?email=taylor%40example.com&signature=' . $signature,
            URL::signedRoute('invites.show', [
                'invite' => 42,
                'email' => 'taylor@example.com',
            ])
        );
    }

    public function testTemporarySignedRouteAddsExpiresBeforeSigning(): void
    {
        Route::get('/files/{file}', 'FileController@show')->name('files.show');

        $expires = 1893456000;
        $signature = hash_hmac(
            'sha256',
            '/files/report?expires=1893456000',
            'testing-secret'
        );

        $this->assertSame(
            '/files/report?expires=1893456000&signature=' . $signature,
            URL::temporarySignedRoute('files.show', $expires, ['file' => 'report'])
        );
    }

    public function testSignedRouteSortsQueryParametersBeforeSigning(): void
    {
        Route::get('/download/{file}', 'DownloadController@show')->name('download.show');

        $signature = hash_hmac(
            'sha256',
            '/download/report.pdf?a=first&z=last',
            'testing-secret'
        );

        $this->assertSame(
            '/download/report.pdf?a=first&signature=' . $signature . '&z=last',
            URL::signedRoute('download.show', [
                'file' => 'report.pdf',
                'z' => 'last',
                'a' => 'first',
            ])
        );
    }

    public function testSignedRoutePreservesDottedQueryParameterKeys(): void
    {
        Route::get('/probe', 'ProbeController@show')->name('probe');

        $signature = hash_hmac(
            'sha256',
            '/probe?a.b=c&z=last',
            'testing-secret'
        );

        $this->assertSame(
            '/probe?a.b=c&signature=' . $signature . '&z=last',
            URL::signedRoute('probe', [
                'a.b' => 'c',
                'z' => 'last',
            ])
        );
    }

    public function testSignedRouteUsesDecodedBase64AppKey(): void
    {
        Route::get('/probe', 'ProbeController@show')->name('probe');
        config(['app.key' => 'base64:' . base64_encode('decoded-secret')]);

        $signature = hash_hmac(
            'sha256',
            '/probe?a=b',
            'decoded-secret'
        );

        $this->assertSame(
            '/probe?a=b&signature=' . $signature,
            URL::signedRoute('probe', ['a' => 'b'])
        );
    }

    public function testHasValidSignatureUsesRawQueryStringForDottedKeys(): void
    {
        Route::get('/probe', 'ProbeController@show')->name('probe');

        $url = URL::signedRoute('probe', [
            'a.b' => 'c',
            'z' => 'last',
        ]);

        $request = new Request(
            [
                'a_b' => 'c',
                'z' => 'last',
                'signature' => $this->signatureFromUrl($url),
            ],
            [],
            [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => $url,
            ],
            []
        );

        $this->assertTrue(SignedUrl::hasValidSignature($request));
    }

    public function testSignedRouteRejectsReservedSignatureParameter(): void
    {
        Route::get('/download/{file}', 'DownloadController@show')->name('download.show');

        $this->assertThrows(\InvalidArgumentException::class, function (): void {
            URL::signedRoute('download.show', [
                'file' => 'report.pdf',
                'signature' => 'user-supplied',
            ]);
        });
    }

    public function testTemporarySignedRouteRejectsReservedExpiresParameter(): void
    {
        Route::get('/download/{file}', 'DownloadController@show')->name('download.show');

        $this->assertThrows(\InvalidArgumentException::class, function (): void {
            URL::temporarySignedRoute('download.show', 1893456000, [
                'file' => 'report.pdf',
                'expires' => 1,
            ]);
        });
    }

    public function testSignedRouteRejectsBracketedSignatureParameter(): void
    {
        Route::get('/probe', 'ProbeController@show')->name('probe');

        $this->assertThrows(\InvalidArgumentException::class, function (): void {
            URL::signedRoute('probe', ['signature[]' => 'x']);
        });
    }

    public function testSignedRouteRejectsBracketedExpiresParameter(): void
    {
        Route::get('/probe', 'ProbeController@show')->name('probe');

        $this->assertThrows(\InvalidArgumentException::class, function (): void {
            URL::signedRoute('probe', ['expires[]' => 'x']);
        });
    }

    public function testHasValidSignatureRejectsBracketedSignaturePollution(): void
    {
        $signature = hash_hmac(
            'sha256',
            '/probe?signature%5B%5D=x',
            'testing-secret'
        );

        $request = $this->requestFromUrl('/probe?signature=' . $signature . '&signature%5B%5D=x');

        $this->assertFalse(SignedUrl::hasValidSignature($request));
    }

    public function testHasValidSignatureRejectsBracketedExpiresPollution(): void
    {
        $signature = hash_hmac(
            'sha256',
            '/probe?expires%5B%5D=abc',
            'testing-secret'
        );

        $request = $this->requestFromUrl('/probe?expires%5B%5D=abc&signature=' . $signature);

        $this->assertFalse(SignedUrl::hasValidSignature($request));
    }

    public function testHasValidSignatureRejectsDuplicateSignatureParameters(): void
    {
        $signature = hash_hmac('sha256', '/probe', 'testing-secret');

        $request = $this->requestFromUrl('/probe?signature=invalid&signature=' . $signature);

        $this->assertFalse(SignedUrl::hasValidSignature($request));
    }

    public function testHasValidSignatureRejectsDuplicateExpiresParameters(): void
    {
        $expires = (string) (time() + 3600);
        $signature = hash_hmac('sha256', '/probe?expires=' . $expires, 'testing-secret');

        $request = $this->requestFromUrl('/probe?expires=1&expires=' . $expires . '&signature=' . $signature);

        $this->assertFalse(SignedUrl::hasValidSignature($request));
    }

    public function testHasValidSignatureRejectsMalformedExpiresParameter(): void
    {
        $signature = hash_hmac(
            'sha256',
            '/probe?expires=abc',
            'testing-secret'
        );

        $request = $this->requestFromUrl('/probe?expires=abc&signature=' . $signature);

        $this->assertFalse(SignedUrl::hasValidSignature($request));
    }

    public function testHasValidSignatureRejectsAddedSignedQueryParameter(): void
    {
        Route::get('/probe', 'ProbeController@show')->name('probe');

        $url = URL::signedRoute('probe', ['a' => 'b']);

        $this->assertFalse(SignedUrl::hasValidSignature($this->requestFromUrl($url . '&extra=x')));
    }

    public function testHasValidSignatureRejectsRemovedSignedQueryParameter(): void
    {
        Route::get('/probe', 'ProbeController@show')->name('probe');

        $url = URL::signedRoute('probe', ['a' => 'b']);

        $this->assertFalse(SignedUrl::hasValidSignature($this->requestFromUrl('/probe?signature=' . $this->signatureFromUrl($url))));
    }

    public function testSignedRouteRequiresConfiguredAppKey(): void
    {
        Route::get('/download/{file}', 'DownloadController@show')->name('download.show');
        config(['app.key' => '']);

        $this->assertThrows(\RuntimeException::class, function (): void {
            URL::signedRoute('download.show', ['file' => 'report.pdf']);
        });
    }

    private function signatureFromUrl(string $url): string
    {
        $queryString = parse_url($url, PHP_URL_QUERY) ?? '';

        foreach (explode('&', $queryString) as $pair) {
            if (str_starts_with($pair, 'signature=')) {
                return urldecode(substr($pair, 10));
            }
        }

        return '';
    }

    private function requestFromUrl(string $url): Request
    {
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);

        return new Request(
            $query,
            [],
            [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => $url,
            ],
            []
        );
    }
}
