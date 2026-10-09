<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Build;

use Phar;
use PharFileInfo;
use RecursiveIteratorIterator;
use Throwable;

final class PharArchiveReader implements ArchiveReader
{
    private readonly Phar $phar;

    public function __construct(string $path)
    {
        try {
            $this->phar = new Phar($path);
        } catch (Throwable $error) {
            throw new BuildException("Cannot open PHAR {$path}: {$error->getMessage()}", 0, $error);
        }
    }

    public function entries(): iterable
    {
        $iterator = new RecursiveIteratorIterator($this->phar);
        foreach ($iterator as $entry) {
            if (!$entry instanceof PharFileInfo) {
                continue;
            }
            if (!$entry->isFile()) {
                continue;
            }
            $contents = $entry->getContent();
            $relative = $entry->getPathName();
            $prefix = 'phar://' . $this->phar->getPath() . '/';
            if (str_starts_with($relative, $prefix)) {
                $relative = substr($relative, strlen($prefix));
            }
            yield new ArchiveEntry($relative, $contents, $entry->isLink());
        }
    }
}
