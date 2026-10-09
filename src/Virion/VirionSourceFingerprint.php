<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Virion;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class VirionSourceFingerprint
{
    public function hash(string $source): string
    {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }
            if ($entry->isLink()) {
                throw new VirionException("Refusing symbolic link in prepared source {$entry->getPathname()}.");
            }
            if ($entry->isFile()) {
                $hash = hash_file('sha256', $entry->getPathname());
                if ($hash === false) {
                    throw new VirionException("Cannot hash prepared source {$entry->getPathname()}.");
                }
                $files[str_replace('\\', '/', substr($entry->getPathname(), strlen($source) + 1))] = $hash;
            }
        }
        ksort($files, SORT_STRING);
        return hash('sha256', json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
