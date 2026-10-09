<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Virion;

use function is_dir;
use function is_file;
use function is_link;

use Phar;

use function realpath;
use function str_ends_with;
use function str_replace;
use function strtolower;

use Throwable;

final class VirionProjectFactory
{
    public function __construct(private readonly VirionManifestReader $manifestReader) {}

    public function open(string $location): VirionProject
    {
        if (is_link($location)) {
            throw new VirionException("Refusing symbolic-link virion path: {$location}");
        }
        $isPhar = is_file($location) && str_ends_with(strtolower($location), '.phar');
        if ($isPhar) {
            $real = realpath($location);
            if ($real === false) {
                throw new VirionException("Cannot resolve virion PHAR path: {$location}");
            }
            try {
                new Phar($real);
            } catch (Throwable $error) {
                throw new VirionException("Cannot open virion PHAR {$location}: {$error->getMessage()}", 0, $error);
            }
            $root = 'phar://' . str_replace('\\', '/', $real);
        } elseif (is_dir($location)) {
            $real = realpath($location);
            if ($real === false) {
                throw new VirionException("Cannot resolve virion folder path: {$location}");
            }
            $root = $real;
        } else {
            throw new VirionException("Virion path is neither a folder nor a PHAR: {$location}");
        }

        $manifestPath = $root . '/virion.yml';
        if (!is_file($manifestPath)) {
            throw new VirionException("Cannot load virion at {$location}: virion.yml is missing.");
        }
        $manifest = $this->manifestReader->read($manifestPath);
        $source = $root . '/src';
        if (!$isPhar && is_link($source)) {
            throw new VirionException("Cannot load {$manifest->name}: source directory may not be a symbolic link: {$source}.");
        }
        if (!is_dir($source)) {
            throw new VirionException("Cannot load {$manifest->name}: source directory is missing at {$source}.");
        }

        $antigenRoot = $source . '/' . str_replace('\\', '/', $manifest->antigen);
        if (!$isPhar && is_link($antigenRoot)) {
            throw new VirionException("Cannot load {$manifest->name}: antigen source root may not be a symbolic link: {$antigenRoot}.");
        }
        $sourceRoot = is_dir($antigenRoot) ? $antigenRoot : $source;

        return new VirionProject($root, $sourceRoot, $manifest, $isPhar);
    }
}
