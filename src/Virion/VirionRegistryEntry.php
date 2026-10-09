<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Virion;

final class VirionRegistryEntry
{
    /** @param list<string> $classes */
    public function __construct(
        public readonly string $location,
        public readonly ?VirionManifest $manifest,
        public readonly VirionStatus $status,
        public readonly string $message,
        public readonly array $classes = [],
        public readonly bool $asyncSupported = false,
    ) {}

    public function requireManifest(): VirionManifest
    {
        if ($this->manifest === null) {
            throw new \LogicException('A loaded virion registry entry must have a manifest.');
        }

        return $this->manifest;
    }
}
