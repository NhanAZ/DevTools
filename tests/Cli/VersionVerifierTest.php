<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Tests\Cli;

use NhanAZ\DevTools\Tests\TestCase;

final class VersionVerifierTest extends TestCase
{
    public function test_release_version_accepts_only_one_optional_tag_prefix(): void
    {
        $version = $this->releaseVersion();
        foreach ([$version, 'v' . $version] as $expected) {
            [$exit, $output] = $this->runScript('verify-version.php', [$expected]);
            self::assertSame(0, $exit, $output);
        }
        foreach (['vv' . $version, 'vvv' . $version] as $expected) {
            [$exit, $output] = $this->runScript('verify-version.php', [$expected]);
            self::assertSame(1, $exit, $output);
            self::assertStringContainsString('Expected a semantic release version', $output);
        }
    }

    public function test_artifact_version_accepts_only_one_optional_tag_prefix(): void
    {
        $root = dirname(__DIR__, 2);
        $result = $this->builder()->build($root, $this->temporaryDirectory . '/virions', $this->temporaryDirectory);
        $version = $this->releaseVersion();
        foreach ([$version, 'v' . $version] as $expected) {
            [$exit, $output] = $this->runScript('validate-artifact.php', [$result->outputPath, $expected]);
            self::assertSame(0, $exit, $output);
        }
        foreach (['vv' . $version, 'vvv' . $version] as $expected) {
            [$exit, $output] = $this->runScript('validate-artifact.php', [$result->outputPath, $expected]);
            self::assertSame(1, $exit, $output);
            self::assertStringContainsString('plugin.yml version does not match', $output);
        }
    }

    private function releaseVersion(): string
    {
        [$reader] = $this->validationServices();

        return $reader->read(dirname(__DIR__, 2) . '/plugin.yml')->description->getVersion();
    }

    /**
     * @param list<string> $arguments
     * @return array{int, string}
     */
    private function runScript(string $script, array $arguments): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/bin/' . $script, ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertIsString($stdout);
        self::assertIsString($stderr);

        return [proc_close($process), $stdout . $stderr];
    }
}
