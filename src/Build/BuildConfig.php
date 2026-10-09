<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Build;

use NhanAZ\DevTools\Virion\VirionRequirement;

final class BuildConfig
{
    /**
     * @param list<VirionRequirement> $virions
     * @param list<string> $includePaths
     */
    public function __construct(
        public readonly array $virions,
        public readonly array $includePaths,
    ) {}
}
