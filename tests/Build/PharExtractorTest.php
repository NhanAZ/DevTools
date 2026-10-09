<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Tests\Build;

use NhanAZ\DevTools\Build\ArchiveEntry;
use NhanAZ\DevTools\Build\BuildException;
use NhanAZ\DevTools\Build\PharArchiveReader;
use NhanAZ\DevTools\Build\PharExtractor;
use NhanAZ\DevTools\Tests\TestCase;

final class PharExtractorTest extends TestCase
{
    public function test_built_phar_can_be_safely_extracted(): void
    {
        $project = $this->copyFixture('BuildPlugin', 'plugins/BuildPlugin');
        $virions = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'virions';
        $this->copyFixture('SharedVirion', 'virions/SharedVirion');
        $result = $this->builder()->build($project, $virions, $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'build');
        $destination = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'extracted';

        $count = (new PharExtractor($this->filesystem))->extract(new PharArchiveReader($result->outputPath), $destination);

        self::assertGreaterThan(2, $count);
        self::assertFileExists($destination . DIRECTORY_SEPARATOR . 'plugin.yml');
        self::assertSame('resource included', trim((string) file_get_contents($destination . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'message.txt')));
    }

    /** @dataProvider unsafePathProvider */
    public function test_malicious_paths_are_rejected(string $path): void
    {
        $extractor = new PharExtractor($this->filesystem);
        $destination = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'extracted';

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('unsafe archive entry');

        $extractor->extract(new FakeArchiveReader([new ArchiveEntry($path, 'malicious')]), $destination);
    }

    /** @return iterable<string, array{string}> */
    public static function unsafePathProvider(): iterable
    {
        yield 'parent traversal' => ['../outside.php'];
        yield 'absolute unix path' => ['/outside.php'];
        yield 'windows drive path' => ['C:\\outside.php'];
        yield 'windows drive-relative path' => ['C:outside.php'];
        yield 'null byte' => ["safe.php\0outside.php"];
        yield 'alternate data stream' => ['safe.php:stream'];
        yield 'trailing dot' => ['safe./file.php'];
        yield 'reserved device' => ['CON/file.php'];
    }

    public function test_existing_file_is_not_overwritten_without_permission(): void
    {
        $destination = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'extracted';
        $this->filesystem->ensureDirectory($destination);
        file_put_contents($destination . DIRECTORY_SEPARATOR . 'plugin.yml', 'original');

        try {
            (new PharExtractor($this->filesystem))->extract(
                new FakeArchiveReader([new ArchiveEntry('plugin.yml', 'replacement')]),
                $destination,
            );
            self::fail('Expected overwrite protection.');
        } catch (BuildException $error) {
            self::assertStringContainsString('Refusing to overwrite', $error->getMessage());
        }
        self::assertSame('original', file_get_contents($destination . DIRECTORY_SEPARATOR . 'plugin.yml'));
    }

    public function test_file_directory_prefix_conflict_is_rejected_before_writing(): void
    {
        $destination = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'extracted';

        try {
            (new PharExtractor($this->filesystem))->extract(
                new FakeArchiveReader([
                    new ArchiveEntry('first.php', 'first'),
                    new ArchiveEntry('path', 'file'),
                    new ArchiveEntry('path/child.php', 'child'),
                ]),
                $destination,
            );
            self::fail('Expected prefix conflict protection.');
        } catch (BuildException $error) {
            self::assertStringContainsString('prefix conflict', $error->getMessage());
        }
        self::assertFileDoesNotExist($destination . DIRECTORY_SEPARATOR . 'first.php');
    }

    public function test_destination_symlink_is_rejected(): void
    {
        $outside = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'outside';
        $destination = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'linked';
        $this->filesystem->ensureDirectory($outside);
        set_error_handler(static fn(): bool => true);
        try {
            $linked = symlink($outside, $destination);
        } finally {
            restore_error_handler();
        }
        if (!$linked) {
            self::markTestSkipped('Creating directory symlinks is not permitted on this system.');
        }

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('symbolic-link extraction destination');

        (new PharExtractor($this->filesystem))->extract(
            new FakeArchiveReader([new ArchiveEntry('plugin.yml', 'safe')]),
            $destination,
        );
    }

    public function test_overwrite_is_installed_transactionally_and_preserves_other_files(): void
    {
        $destination = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'extracted';
        $this->filesystem->ensureDirectory($destination);
        file_put_contents($destination . DIRECTORY_SEPARATOR . 'plugin.yml', 'old');
        file_put_contents($destination . DIRECTORY_SEPARATOR . 'keep.txt', 'keep');

        $count = (new PharExtractor($this->filesystem))->extract(
            new FakeArchiveReader([
                new ArchiveEntry('plugin.yml', 'new'),
                new ArchiveEntry('src/Main.php', '<?php'),
            ]),
            $destination,
            true,
        );

        self::assertSame(2, $count);
        self::assertSame('new', file_get_contents($destination . DIRECTORY_SEPARATOR . 'plugin.yml'));
        self::assertSame('keep', file_get_contents($destination . DIRECTORY_SEPARATOR . 'keep.txt'));
        self::assertFileExists($destination . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Main.php');
    }
}
