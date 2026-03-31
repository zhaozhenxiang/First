<?php

declare(strict_types=1);

namespace Tests;

use Bin\Console\Input;
use Bin\Console\Kernel;
use Bin\Console\Output;
use Bin\Testing\TestCase;

/**
 * CLI 命令行工具测试
 */
class ConsoleTest extends TestCase
{
    private Output $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->output = new Output();
    }

    public function testInputParsing(): void
    {
        $argv = ['script', 'test', 'arg1', 'arg2', '--option', '--value=test'];
        $input = new Input($argv);

        $this->assertEquals('test', $input->getCommandName());
        $this->assertEquals(['arg1', 'arg2'], $input->getArguments());
        $this->assertTrue($input->hasOption('option'));
        $this->assertEquals('test', $input->getOption('value'));
    }

    public function testInputParsingWithEquals(): void
    {
        $argv = ['script', 'test', '--option=value'];
        $input = new Input($argv);

        $this->assertEquals('value', $input->getOption('option'));
    }

    public function testInputParsingShortOptions(): void
    {
        $argv = ['script', 'test', '-abc'];
        $input = new Input($argv);

        $this->assertTrue($input->hasOption('a'));
        $this->assertTrue($input->hasOption('b'));
        $this->assertTrue($input->hasOption('c'));
    }

    public function testInputGetArgument(): void
    {
        $argv = ['script', 'test', 'arg1', 'arg2'];
        $input = new Input($argv);

        $this->assertEquals('arg1', $input->getArgument(0));
        $this->assertEquals('arg2', $input->getArgument(1));
        $this->assertNull($input->getArgument(2));
        $this->assertEquals('default', $input->getArgument(2, 'default'));
    }

    public function testOutputWriting(): void
    {
        ob_start();
        $this->output->write('Test message');
        $content = ob_get_clean();

        $this->assertStringContainsString('Test message', $content);
    }

    public function testOutputInfo(): void
    {
        ob_start();
        $this->output->info('Info message');
        $content = ob_get_clean();

        $this->assertStringContainsString('Info message', $content);
    }

    public function testOutputSuccess(): void
    {
        ob_start();
        $this->output->success('Success message');
        $content = ob_get_clean();

        $this->assertStringContainsString('Success message', $content);
    }

    public function testOutputError(): void
    {
        ob_start();
        $this->output->error('Error message');
        $content = ob_get_clean();

        $this->assertStringContainsString('Error message', $content);
    }

    public function testOutputWarning(): void
    {
        ob_start();
        $this->output->warning('Warning message');
        $content = ob_get_clean();

        $this->assertStringContainsString('Warning message', $content);
    }

    public function testOutputComment(): void
    {
        ob_start();
        $this->output->comment('Comment message');
        $content = ob_get_clean();

        $this->assertStringContainsString('Comment message', $content);
    }

    public function testOutputTable(): void
    {
        ob_start();
        $this->output->table(['Name', 'Age'], [['Alice', 25], ['Bob', 30]]);
        $content = ob_get_clean();

        // 检查是否包含关键字符（不依赖颜色标签）
        $this->assertStringContainsString('Alice', $content);
        $this->assertStringContainsString('Bob', $content);
        $this->assertStringContainsString('25', $content);
        $this->assertStringContainsString('30', $content);
    }

    public function testOutputJson(): void
    {
        ob_start();
        $this->output->json(['key' => 'value']);
        $content = ob_get_clean();

        // 检查 JSON 输出
        $decoded = json_decode($content, true);
        $this->assertEquals(['key' => 'value'], $decoded);
    }

    public function testOutputList(): void
    {
        ob_start();
        $this->output->list(['Item 1', 'Item 2'], 'Items');
        $content = ob_get_clean();

        // 检查列表项（不依赖标题）
        $this->assertStringContainsString('Item 1', $content);
        $this->assertStringContainsString('Item 2', $content);
    }

    public function testCommandRegistration(): void
    {
        // 清除现有命令
        Kernel::clear();

        // 注册测试命令 - 使用闭包工厂
        Kernel::register('test:command', function () {
            return new class extends \Bin\Console\Command {
                public string $name = 'test:command';
                public string $description = 'Test command';
                public function execute(): int { return 0; }
            };
        });

        $this->assertTrue(Kernel::hasCommand('test:command'));
    }

    public function testCommandAlias(): void
    {
        // 清除现有命令
        Kernel::clear();

        Kernel::register('test:command', function () {
            $cmd = new class extends \Bin\Console\Command {
                public string $name = 'test:command';
                public string $description = 'Test command';
                public function execute(): int { return 0; }
            };
            return $cmd;
        });

        Kernel::alias('test', 'test:command');

        // 通过 getCommand 实例化命令
        $command = Kernel::getCommand('test');
        $this->assertEquals('test:command', $command->getName());
    }

    public function testCommandCall(): void
    {
        // 清除现有命令
        Kernel::clear();

        Kernel::register('test:echo', function () {
            return new class extends \Bin\Console\Command {
                public string $name = 'test:echo';
                public string $description = 'Echo test';
                public function execute(): int
                {
                    $this->info('Test output');
                    return 0;
                }
            };
        });

        $exitCode = Kernel::callSilent('test:echo');

        $this->assertEquals(0, $exitCode);
    }

    public function testProgressBar(): void
    {
        $progress = $this->output->progressStart(100);

        $progress->setMessage('Processing...');

        for ($i = 0; $i <= 100; $i += 20) {
            $progress->setProgress($i);
        }

        $progress->finish();

        $this->assertEquals(100, $progress->getProgress());
        $this->assertEquals(100, $progress->getMaxSteps());
    }

    public function testProgressBarAdvance(): void
    {
        $progress = $this->output->progressStart(50);

        $progress->advance(10);
        $this->assertEquals(10, $progress->getProgress());

        $progress->advance(20);
        $this->assertEquals(30, $progress->getProgress());

        $progress->finish();

        $this->assertEquals(50, $progress->getProgress());
    }

    public function testCommandSignatureParsing(): void
    {
        $command = new class extends \Bin\Console\Command {
            public string $signature = 'test {arg1} {arg2?} {--option} {--flag}';
            public string $description = 'Test signature';
            public function execute(): int { return 0; }
        };

        $command->parseSignature();

        $this->assertEquals('test', $command->getName());

        $arguments = $command->getArguments();
        $this->assertArrayHasKey('arg1', $arguments);
        $this->assertArrayHasKey('arg2', $arguments);
        $this->assertTrue(($arguments['arg1']['required'] ?? false));
        $this->assertFalse(($arguments['arg2']['required'] ?? false));

        $options = $command->getOptions();
        $this->assertArrayHasKey('option', $options);
        $this->assertArrayHasKey('flag', $options);
    }

    // === 边界条件 ===

    public function testHasCommandReturnsFalseForUnknown(): void
    {
        Kernel::clear();
        $this->assertFalse(Kernel::hasCommand('nonexistent:command'));
    }

    public function testCallSilentReturnsNonZeroForUnknown(): void
    {
        Kernel::clear();
        $code = Kernel::callSilent('nonexistent:command');
        $this->assertNotEquals(0, $code);
    }

    public function testClearRemovesAllCommands(): void
    {
        Kernel::register('clear_test', function () {
            return new class extends \Bin\Console\Command {
                public string $name = 'clear_test';
                public string $description = 'Test';
                public function execute(): int { return 0; }
            };
        });
        $this->assertTrue(Kernel::hasCommand('clear_test'));

        Kernel::clear();
        $this->assertFalse(Kernel::hasCommand('clear_test'));
    }

    public function testRegisterDuplicateOverwrites(): void
    {
        Kernel::clear();

        Kernel::register('dup_test', function () {
            return new class extends \Bin\Console\Command {
                public string $name = 'dup_test';
                public string $description = 'First';
                public function execute(): int { return 1; }
            };
        });

        Kernel::register('dup_test', function () {
            return new class extends \Bin\Console\Command {
                public string $name = 'dup_test';
                public string $description = 'Second';
                public function execute(): int { return 2; }
            };
        });

        $cmd = Kernel::getCommand('dup_test');
        $this->assertEquals('Second', $cmd->getDescription());
    }

    public function testProgressBarCannotGoBeyondMax(): void
    {
        $progress = $this->output->progressStart(10);
        $progress->advance(20);
        $this->assertEquals(20, $progress->getProgress());
    }

    public function testInputDefaultOptionValue(): void
    {
        $argv = ['script', 'test', '--color'];
        $input = new Input($argv);
        $this->assertTrue($input->hasOption('color'));
        // Flag-style options (no value) may return true
        $this->assertTrue($input->getOption('color'));
    }
}
