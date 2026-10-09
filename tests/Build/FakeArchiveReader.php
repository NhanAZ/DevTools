<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Tests\Build;

use NhanAZ\DevTools\Build\ArchiveEntry;
use NhanAZ\DevTools\Build\ArchiveReader;

final class FakeArchiveReader implements ArchiveReader
{
    /** @param list<ArchiveEntry> $entries */
    public function __construct(private readonly array $entries) {}

    public function entries(): iterable
    {
        yield from $this->entries;
    }
}
