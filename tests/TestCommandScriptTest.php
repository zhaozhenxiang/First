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

        $command = 'php ' . escapeshellarg($script) . ' ' . escapeshellarg($testFile);

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

        $this->assertStringNotContainsString('data_set(): Argument #1 ($data) must be of type array, null given', $rendered);
        $this->assertStringNotContainsString('Session cannot be started after headers have already been sent', $rendered);
        $this->assertStringContainsString('Passed: 26, Failed: 0', $rendered);
        $this->assertEquals(0, $exitCode);
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
        $this->assertStringContainsString('Passed: 24, Failed: 0', $rendered);
        $this->assertStringNotContainsString('Cannot modify header information', $rendered);
    }

    public function testRootTestScriptReturnsNonZeroForUnhandledErrorInSuiteMode(): void
    {
        $script = basePath('test');
        $tempDir = sys_get_temp_dir() . '/test_command_error_suite_' . uniqid();

        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir . '/FailingErrorTest.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;

class FailingErrorTest extends TestCase
{
    public function testThrowsError(): void
    {
        \Does\Not\Exist::boom();
    }
}
PHP);

        try {
            $command = 'php ' . escapeshellarg($script) . ' ' . escapeshellarg($tempDir) . ' 2>&1';

            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);

            $rendered = implode("\n", $output);

            $this->assertEquals(1, $exitCode);
            $this->assertStringContainsString('Class "Does\Not\Exist" not found', $rendered);
            $this->assertStringContainsString('Tests:  1, ✗ 1 failed, ✓ 0 passed', $rendered);
            $this->assertStringNotContainsString('PHP Fatal error', $rendered);
        } finally {
            unlink($tempDir . '/FailingErrorTest.php');
            rmdir($tempDir);
        }
    }

    public function testRootTestScriptReturnsNonZeroForTearDownErrorInSuiteMode(): void
    {
        $script = basePath('test');
        $tempDir = sys_get_temp_dir() . '/test_command_teardown_suite_' . uniqid();

        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir . '/FailingTearDownTest.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;

class FailingTearDownTest extends TestCase
{
    protected function tearDown(): void
    {
        \Does\Not\Exist::boom();
    }

    public function testPassesUntilTearDown(): void
    {
        // noop
    }
}
PHP);

        try {
            $command = 'php ' . escapeshellarg($script) . ' ' . escapeshellarg($tempDir) . ' 2>&1';

            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);

            $rendered = implode("\n", $output);

            $this->assertEquals(1, $exitCode);
            $this->assertStringContainsString('Class "Does\Not\Exist" not found', $rendered);
            $this->assertStringContainsString('Tests:  1, ✗ 1 failed, ✓ 0 passed', $rendered);
            $this->assertStringNotContainsString('PHP Fatal error', $rendered);
        } finally {
            unlink($tempDir . '/FailingTearDownTest.php');
            rmdir($tempDir);
        }
    }
}
