<?php

declare(strict_types=1);

namespace Tests;

use Bin\Facade\URL;
use Bin\Route\RouteCollection as Route;
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

    public function testSignedRouteRequiresConfiguredAppKey(): void
    {
        Route::get('/download/{file}', 'DownloadController@show')->name('download.show');
        config(['app.key' => '']);

        $this->assertThrows(\RuntimeException::class, function (): void {
            URL::signedRoute('download.show', ['file' => 'report.pdf']);
        });
    }
}
