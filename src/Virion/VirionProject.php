<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Virion;

final class VirionProject
{
    public function __construct(
        public readonly string $location,
        public readonly string $sourceRoot,
        public readonly VirionManifest $manifest,
        public readonly bool $phar,
    ) {}
}
