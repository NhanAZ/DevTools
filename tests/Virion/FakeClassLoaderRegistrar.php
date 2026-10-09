<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Tests\Virion;

use NhanAZ\DevTools\Virion\ClassLoaderRegistrar;

final class FakeClassLoaderRegistrar implements ClassLoaderRegistrar
{
    /** @var list<array{string, string}> */
    public array $registrations = [];

    public function register(string $namespacePrefix, string $sourceRoot): void
    {
        $this->registrations[] = [$namespacePrefix, $sourceRoot];
    }

    public function supportsAsyncWorkers(): bool
    {
        return true;
    }

    public function asyncSupportDescription(): string
    {
        return 'test thread-safe loader';
    }
}
