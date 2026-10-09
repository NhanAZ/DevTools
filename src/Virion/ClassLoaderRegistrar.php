<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Virion;

interface ClassLoaderRegistrar
{
    public function register(string $namespacePrefix, string $sourceRoot): void;

    public function supportsAsyncWorkers(): bool;

    public function asyncSupportDescription(): string;
}
