<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Support;

final class PhpFileSymbols
{
    /**
     * @param list<string> $classes
     * @param list<string> $namespaces
     */
    public function __construct(
        public readonly array $classes,
        public readonly array $namespaces,
    ) {}
}
