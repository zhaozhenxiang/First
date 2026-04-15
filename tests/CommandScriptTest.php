<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;

class CommandScriptTest extends TestCase
{
    public function testRootCommandScriptShowsHelpInsteadOfInternalServerError(): void
    {
        $script = basePath('command');
        $command = 'php ' . escapeshellarg($script);

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $rendered = implode("\n", $output);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Available Commands', $rendered);
        $this->assertStringNotContainsString('Internal Server Error', $rendered);
    }

    public function testRootCommandScriptShowsSpecificCommandHelp(): void
    {
        $script = basePath('command');
        $command = 'php ' . escapeshellarg($script) . ' help test';

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $rendered = implode("\n", $output);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Run the application tests', $rendered);
        $this->assertStringContainsString('Usage', $rendered);
        $this->assertStringNotContainsString('Available Commands', $rendered);
    }

    public function testRootCommandScriptReturnsFailureForUnknownHelpTarget(): void
    {
        $script = basePath('command');
        $command = 'php ' . escapeshellarg($script) . ' help nonexistent 2>&1';

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $rendered = implode("\n", $output);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Command not found: nonexistent', $rendered);
        $this->assertStringNotContainsString('Available Commands', $rendered);
    }
}
