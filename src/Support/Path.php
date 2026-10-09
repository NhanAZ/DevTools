<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Support;

use function array_shift;
use function ctype_alpha;
use function explode;
use function implode;
use function in_array;
use function preg_match;
use function rtrim;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

final class Path
{
    /** @var list<string> */
    private const DEFAULT_EXCLUDED_COMPONENTS = [
        '.git',
        '.github',
        '.idea',
        '.vscode',
        '.cache',
        '.phpunit.cache',
        '.phpstan-cache',
        'build',
        'cache',
        'node_modules',
        'tests',
        'tmp',
        'vendor',
    ];

    public static function join(string ...$parts): string
    {
        $first = rtrim(array_shift($parts) ?? '', '/\\');
        $parts = array_map(static fn(string $part): string => trim($part, '/\\'), $parts);

        return $first . ($parts === [] ? '' : DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts));
    }

    public static function normalizeRelative(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new FilesystemException('A path contains a null byte.');
        }

        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1) {
            throw new FilesystemException("The path must be relative: {$path}");
        }

        $normalized = [];
        foreach (explode('/', $path) as $component) {
            if ($component === '' || $component === '.') {
                continue;
            }
            if ($component === '..') {
                throw new FilesystemException("The path escapes its destination: {$path}");
            }
            if (preg_match('/[<>:"|?*]/', $component) === 1 || preg_match('/[. ]$/', $component) === 1) {
                throw new FilesystemException("The path is not portable across supported filesystems: {$path}");
            }
            $device = strtolower(explode('.', $component, 2)[0]);
            if (preg_match('/^(?:con|prn|aux|nul|com[1-9]|lpt[1-9])$/D', $device) === 1) {
                throw new FilesystemException("The path uses a reserved Windows device name: {$path}");
            }
            $normalized[] = $component;
        }

        if ($normalized === []) {
            throw new FilesystemException("The path does not name a file: {$path}");
        }

        return implode('/', $normalized);
    }

    public static function isExcluded(string $relativePath): bool
    {
        foreach (explode('/', str_replace('\\', '/', $relativePath)) as $component) {
            if ($component === '') {
                continue;
            }
            if (str_starts_with($component, '.') || in_array(strtolower($component), self::DEFAULT_EXCLUDED_COMPONENTS, true)) {
                return true;
            }
            if (str_ends_with(strtolower($component), '.tmp') || str_ends_with(strtolower($component), '~')) {
                return true;
            }
        }

        return false;
    }

    public static function isHiddenOrTemporary(string $relativePath): bool
    {
        foreach (explode('/', str_replace('\\', '/', $relativePath)) as $component) {
            if ($component === '') {
                continue;
            }
            $lower = strtolower($component);
            if (str_starts_with($component, '.') || str_ends_with($lower, '.tmp') || str_ends_with($lower, '~')) {
                return true;
            }
        }

        return false;
    }

    public static function isSensitive(string $relativePath): bool
    {
        foreach (explode('/', str_replace('\\', '/', $relativePath)) as $component) {
            $lower = strtolower($component);
            if ($lower === '.env' || str_starts_with($lower, '.env.')
                || in_array($lower, ['auth.json', 'credentials.json', 'id_rsa', 'id_ed25519'], true)
                || preg_match('/\.(?:key|pem|p12|pfx)$/D', $lower) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function isInside(string $base, string $candidate): bool
    {
        $base = self::comparable($base);
        $candidate = self::comparable($candidate);

        return $candidate === $base || str_starts_with($candidate, $base . '/');
    }

    private static function comparable(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        if (isset($path[1]) && $path[1] === ':' && ctype_alpha($path[0])) {
            $path = strtolower(substr($path, 0, 2)) . substr($path, 2);
        }

        return $path;
    }
}
