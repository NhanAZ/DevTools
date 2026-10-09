<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Virion;

use FilesystemIterator;
use NhanAZ\DevTools\Support\PhpSourceInspector;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;

final class VirionClassScanner
{
    public function __construct(private readonly PhpSourceInspector $sourceInspector) {}

    /** @return list<string> */
    public function scan(VirionProject $project): array
    {
        $classes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($project->sourceRoot, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }
            if ($entry->isLink()) {
                throw new VirionException("Cannot load {$project->manifest->name}: symbolic link found at {$entry->getPathname()}.");
            }
            if (!$entry->isFile() || !str_ends_with(strtolower($entry->getFilename()), '.php')) {
                continue;
            }
            foreach ($this->sourceInspector->inspect($entry->getPathname())->classes as $class) {
                $classes[] = $class;
            }
        }
        sort($classes, SORT_STRING);
        foreach (array_unique($classes) as $class) {
            if (!str_starts_with($class, $project->manifest->antigen . '\\')) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($class, strlen($project->manifest->antigen) + 1)) . '.php';
            if (!$this->caseSensitiveFileExists($project->sourceRoot, $relative)) {
                throw new VirionException(
                    "Cannot load {$project->manifest->name}: expected source file {$project->sourceRoot}/{$relative} for class {$class}. "
                    . 'Correct the antigen, class namespace, filename, or directory casing.',
                );
            }
        }

        return $classes;
    }

    private function caseSensitiveFileExists(string $base, string $relative): bool
    {
        $current = $base;
        foreach (explode('/', $relative) as $component) {
            $entries = scandir($current);
            if ($entries === false || !in_array($component, $entries, true)) {
                return false;
            }
            $current .= DIRECTORY_SEPARATOR . $component;
        }

        return is_file($current);
    }
}
