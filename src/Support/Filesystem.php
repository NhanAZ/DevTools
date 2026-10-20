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
    public static function isLink(string $path): bool
    {
        $path = rtrim($path, '/\\');
        if (is_link($path)) {
            return true;
        }
        if (PHP_OS_FAMILY !== 'Windows' || $path === '' || preg_match('/^[A-Za-z]:$/D', $path) === 1
            || in_array(basename($path), ['.', '..'], true)) {
            return false;
        }

        // Windows junctions resolve to another directory but are not reported by is_link().
        $resolved = realpath($path);
        $parent = realpath(dirname($path));
        if ($resolved === false || $parent === false
            || strcasecmp($resolved, Path::join($parent, basename($path))) === 0) {
            return false;
        }

        // An NTFS short name still identifies the same entry; a junction has its own identity.
        $entry = lstat($path);
        $target = lstat($resolved);
        return $entry === false || $target === false || $entry['ino'] === 0
            || $entry['ino'] !== $target['ino'] || $entry['dev'] !== $target['dev'];
    }

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
        if (self::isLink($source)) {
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
            if (self::isLink($entry->getPathname())) {
                throw new FilesystemException("Refusing to copy symbolic link: {$entry->getPathname()}");
            }
            if ($include !== null && !$include($relative)) {
                continue;
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
            if (self::isLink($current)) {
                throw new FilesystemException("Refusing path through symbolic link: {$current}");
            }
        }
    }

    public function copyFile(string $source, string $destination): void
    {
        if (!is_file($source) || self::isLink($source)) {
            throw new FilesystemException("Refusing to copy a missing file or symbolic link: {$source}");
        }
        $this->ensureDirectory(dirname($destination));
        if (!copy($source, $destination)) {
            throw new FilesystemException("Cannot copy {$source} to {$destination}");
        }
    }

    public function removeTree(string $path): void
    {
        if (!file_exists($path) && !self::isLink($path)) {
            return;
        }
        if (self::isLink($path) || is_file($path)) {
            if (!(PHP_OS_FAMILY === 'Windows' && is_dir($path) ? rmdir($path) : unlink($path))) {
                throw new FilesystemException("Cannot remove file: {$path}");
            }

            return;
        }

        foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }
            $this->removeTree($entry->getPathname());
        }
        if (!rmdir($path)) {
            throw new FilesystemException("Cannot remove directory: {$path}");
        }
    }
}
