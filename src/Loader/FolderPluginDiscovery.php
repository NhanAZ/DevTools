<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Loader;

use FilesystemIterator;

use function is_dir;
use function is_file;

use NhanAZ\DevTools\Support\Path;

use function sort;

use SplFileInfo;

use function str_replace;

final class FolderPluginDiscovery
{
    /**
     * Finds immediate children that either contain plugin.yml or contain src/ and therefore look like an incomplete plugin.
     *
     * @return list<string>
     */
    public function discover(string $pluginsDirectory): array
    {
        if (!is_dir($pluginsDirectory)) {
            return [];
        }

        $projects = [];
        $iterator = new FilesystemIterator(
            $pluginsDirectory,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
        );
        foreach ($iterator as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }
            if (!$entry->isDir() || $entry->isLink() || Path::isExcluded($entry->getFilename())) {
                continue;
            }
            $path = $entry->getPathname();
            if (is_file($path . DIRECTORY_SEPARATOR . 'plugin.yml') || is_dir($path . DIRECTORY_SEPARATOR . 'src')) {
                $projects[] = str_replace('\\', '/', $path);
            }
        }
        sort($projects, SORT_STRING);

        return $projects;
    }
}
