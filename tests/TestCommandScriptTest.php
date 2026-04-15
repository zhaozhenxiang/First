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
}
