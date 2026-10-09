<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Support;

use function copy;
use function dirname;
use function file_exists;

use FilesystemIterator;

use function is_dir;
use function is_file;
use function is_link;
use function mkdir;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function rmdir;

use SplFileInfo;

use function unlink;

final class Filesystem
{
    public function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }
        if (file_exists($path) || !mkdir($path, 0777, true)) {
            throw new FilesystemException("Cannot create directory: {$path}");
        }
    }

    /**
     * @param callable(string): bool|null $include Receives a normalized relative path.
     */
    public function copyTree(string $source, string $destination, ?callable $include = null): void
    {
        if (is_link($source)) {
            throw new FilesystemException("Refusing to copy symbolic-link directory: {$source}");
        }
        if (!is_dir($source)) {
            throw new FilesystemException("Source directory does not exist: {$source}");
        }

        $this->ensureDirectory($destination);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($entry->getPathname(), strlen($source) + 1));
            if ($include !== null && !$include($relative)) {
                continue;
            }
            if ($entry->isLink()) {
                throw new FilesystemException("Refusing to copy symbolic link: {$entry->getPathname()}");
            }
            $target = Path::join($destination, $relative);
            if ($entry->isDir()) {
                $this->ensureDirectory($target);
            } elseif ($entry->isFile()) {
                $this->ensureDirectory(dirname($target));
                if (!copy($entry->getPathname(), $target)) {
                    throw new FilesystemException("Cannot copy {$entry->getPathname()} to {$target}");
                }
            }
        }
    }

    public function assertNoSymbolicLinkComponents(string $base, string $relative): void
    {
        $relative = Path::normalizeRelative($relative);
        $current = $base;
        foreach (explode('/', $relative) as $component) {
            $current = Path::join($current, $component);
            if (is_link($current)) {
                throw new FilesystemException("Refusing path through symbolic link: {$current}");
            }
        }
    }

    public function copyFile(string $source, string $destination): void
    {
        if (!is_file($source) || is_link($source)) {
            throw new FilesystemException("Refusing to copy a missing file or symbolic link: {$source}");
        }
        $this->ensureDirectory(dirname($destination));
        if (!copy($source, $destination)) {
            throw new FilesystemException("Cannot copy {$source} to {$destination}");
        }
    }

    public function removeTree(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) {
            return;
        }
        if (is_link($path) || is_file($path)) {
            if (!unlink($path)) {
                throw new FilesystemException("Cannot remove file: {$path}");
            }

            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }
            if ($entry->isLink() || $entry->isFile()) {
                if (!unlink($entry->getPathname())) {
                    throw new FilesystemException("Cannot remove file: {$entry->getPathname()}");
                }
            } elseif (!rmdir($entry->getPathname())) {
                throw new FilesystemException("Cannot remove directory: {$entry->getPathname()}");
            }
        }
        if (!rmdir($path)) {
            throw new FilesystemException("Cannot remove directory: {$path}");
        }
    }
}
