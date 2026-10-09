<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Virion;

use FilesystemIterator;

use function is_dir;

use NhanAZ\DevTools\Support\Filesystem;
use NhanAZ\DevTools\Support\Path;

use function sort;

use SplFileInfo;

use function str_ends_with;
use function strtolower;

final class VirionDiscovery
{
    public function __construct(private readonly Filesystem $filesystem) {}

    /** @return list<string> */
    public function discover(string $directory): array
    {
        $this->filesystem->ensureDirectory($directory);
        $locations = [];
        $iterator = new FilesystemIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
        );
        foreach ($iterator as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }
            if (Path::isExcluded($entry->getFilename()) || $entry->isLink()) {
                continue;
            }
            if ($entry->isDir() || ($entry->isFile() && str_ends_with(strtolower($entry->getFilename()), '.phar'))) {
                $locations[] = $entry->getPathname();
            }
        }
        sort($locations, SORT_STRING);

        return $locations;
    }
}
