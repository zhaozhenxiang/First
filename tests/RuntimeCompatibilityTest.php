<?php

declare(strict_types=1);

namespace Tests;

use Bin\Testing\TestCase;

class RuntimeCompatibilityTest extends TestCase
{
    public function testFrameworkFilesLintWithoutDeprecations(): void
    {
        $failures = [];

        foreach ($this->phpFiles(BASE_PATH . '/bin') as $file) {
            $command = sprintf(
                '%s -d error_reporting=32767 -d display_errors=1 -l %s 2>&1',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($file)
            );

            exec($command, $output, $exitCode);
            $text = implode("\n", $output);

            if ($exitCode !== 0 || str_contains($text, 'Deprecated:') || str_contains($text, 'PHP Deprecated:')) {
                $failures[] = $file . "\n" . $text;
            }
        }

        $this->assertEquals([], $failures, "Framework files emitted lint errors or deprecations:\n" . implode("\n\n", $failures));
    }

    public function testNoDeprecatedReflectionSetAccessibleCallsRemain(): void
    {
        $matches = [];
        $deprecatedCall = '->set' . 'Accessible(';

        foreach ([BASE_PATH . '/bin', BASE_PATH . '/tests'] as $directory) {
            foreach ($this->phpFiles($directory) as $file) {
                $contents = file_get_contents($file);
                if ($contents !== false && str_contains($contents, $deprecatedCall)) {
                    $matches[] = $file;
                }
            }
        }

        $this->assertEquals([], $matches, "Deprecated Reflection::setAccessible() calls remain:\n" . implode("\n", $matches));
    }

    /**
     * @return string[]
     */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
