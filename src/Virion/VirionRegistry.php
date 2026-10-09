<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Virion;

use function strtolower;

final class VirionRegistry
{
    /** @var list<VirionRegistryEntry> */
    private array $entries = [];

    /** @var array<string, VirionRegistryEntry> */
    private array $loadedByName = [];

    /** @var array<string, VirionRegistryEntry> */
    private array $loadedByAntigen = [];

    /** @var array<string, VirionRegistryEntry> */
    private array $loadedByClass = [];

    public function record(VirionRegistryEntry $entry): void
    {
        $this->entries[] = $entry;
        if ($entry->status !== VirionStatus::LOADED || $entry->manifest === null) {
            return;
        }
        $this->loadedByName[strtolower($entry->manifest->name)] = $entry;
        $this->loadedByAntigen[strtolower($entry->manifest->antigen)] = $entry;
        foreach ($entry->classes as $class) {
            $this->loadedByClass[strtolower($class)] = $entry;
        }
    }

    /** @return list<VirionRegistryEntry> */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return list<VirionRegistryEntry> */
    public function loaded(): array
    {
        return array_values($this->loadedByName);
    }

    public function findByName(string $name): ?VirionRegistryEntry
    {
        return $this->loadedByName[strtolower($name)] ?? null;
    }

    public function findByAntigen(string $antigen): ?VirionRegistryEntry
    {
        return $this->loadedByAntigen[strtolower($antigen)] ?? null;
    }

    public function findByClass(string $class): ?VirionRegistryEntry
    {
        return $this->loadedByClass[strtolower($class)] ?? null;
    }
}
