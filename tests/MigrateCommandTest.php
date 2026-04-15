<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;

require_once dirname(__DIR__) . '/bin/Database/Migrations/MigrateCommand.php';

class MigrateCommandTest extends TestCase
{
    public function testMigrateCommandScriptCanBeRequiredWithoutRunningMain(): void
    {
        $script = basePath('bin/Database/Migrations/MigrateCommand.php');

        $command = 'timeout 3 php -r ' . escapeshellarg(
            '$argv = ["embedded-script"]; require ' . var_export($script, true) . '; echo "LOADED";'
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $this->assertEquals(0, $exitCode);
        $this->assertEquals('LOADED', implode("\n", $output));
    }

    public function testRunReturnsFailureCodeForUnknownCommand(): void
    {
        $command = $this->newCommandWithoutConstructor();

        ob_start();
        $exitCode = $command->run(['migrate', 'wat']);
        $output = ob_get_clean();

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Unknown command: wat', $output);
    }

    public function testRunReturnsFailureCodeWhenMakeNameIsMissing(): void
    {
        $script = basePath('bin/Database/Migrations/MigrateCommand.php');
        $command = 'timeout 3 php -r ' . escapeshellarg(
            'require ' . var_export($script, true) . '; '
            . '$reflection = new ReflectionClass("MigrateCommand"); '
            . '$command = $reflection->newInstanceWithoutConstructor(); '
            . '$code = $command->run(["migrate", "make"]); '
            . 'echo "AFTER:" . $code;'
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Migration name is required.', implode("\n", $output));
        $this->assertStringContainsString('Usage: php migrate make:create_migration_name [table]', implode("\n", $output));
        $this->assertStringContainsString('AFTER:1', implode("\n", $output));
    }

    public function testRootMigrateScriptHandlesUnknownCommandWithoutInitializingDatabase(): void
    {
        $script = basePath('migrate');
        $command = 'timeout 3 php ' . escapeshellarg($script) . ' wat';

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $rendered = implode("\n", $output);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Unknown command: wat', $rendered);
    }

    private function newCommandWithoutConstructor(): object
    {
        $reflection = new \ReflectionClass(\MigrateCommand::class);

        return $reflection->newInstanceWithoutConstructor();
    }
}
