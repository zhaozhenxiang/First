<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;

class TestCommandScriptTest extends TestCase
{
    public function testTestCommandScriptCanBeRequiredWithoutRunningMain(): void
    {
        $script = basePath('bin/Testing/TestCommand.php');

        $command = 'php -r ' . escapeshellarg(
            '$argv = ["embedded-script"]; require ' . var_export($script, true) . '; echo "LOADED";'
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('LOADED', implode("\n", $output));
    }

    public function testRootTestScriptRunsTestCommand(): void
    {
        $script = basePath('test');
        $testFile = basePath('tests/ExampleTest.php');

        $command = 'php ' . escapeshellarg($script) . ' ' . escapeshellarg($testFile) . ' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $rendered = implode("\n", $output);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString("Running tests in {$testFile}", $rendered);
        $this->assertStringContainsString('Passed: 11, Failed: 0', $rendered);
    }

    public function testRootTestScriptRunsSessionSuiteWithoutHeaderSentFailure(): void
    {
        $script = basePath('test');
        $testFile = basePath('tests/SessionTest.php');

        $command = 'php ' . escapeshellarg($script) . ' ' . escapeshellarg($testFile) . ' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $rendered = implode("\n", $output);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Passed: 25, Failed: 0', $rendered);
        $this->assertStringNotContainsString('Session cannot be started after headers have already been sent', $rendered);
        $this->assertStringNotContainsString('data_set(): Argument #1 ($data) must be of type array, null given', $rendered);
    }

    public function testRootTestScriptRunsCookieSuiteWithoutHeaderWarnings(): void
    {
        $script = basePath('test');
        $testFile = basePath('tests/CookieUploadTest.php');

        $command = 'php ' . escapeshellarg($script) . ' ' . escapeshellarg($testFile) . ' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $rendered = implode("\n", $output);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Passed: 22, Failed: 0', $rendered);
        $this->assertStringNotContainsString('Cannot modify header information', $rendered);
    }
}
