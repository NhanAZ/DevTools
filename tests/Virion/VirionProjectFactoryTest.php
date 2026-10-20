<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Tests\Virion;

use NhanAZ\DevTools\Support\YamlReader;
use NhanAZ\DevTools\Tests\TestCase;
use NhanAZ\DevTools\Virion\VirionException;
use NhanAZ\DevTools\Virion\VirionManifestReader;
use NhanAZ\DevTools\Virion\VirionProjectFactory;

final class VirionProjectFactoryTest extends TestCase
{
    public function test_manifest_symlink_is_rejected(): void
    {
        $project = $this->copyFixture('SharedVirion');
        $manifest = $project . '/virion.yml';
        $outside = $this->temporaryDirectory . '/external-virion.yml';
        rename($manifest, $outside);
        $this->createSymbolicLink($outside, $manifest);

        $this->expectException(VirionException::class);
        $this->expectExceptionMessage('symbolic link');
        $this->factory()->open($project);
    }

    public function test_intermediate_antigen_symlink_is_rejected(): void
    {
        $project = $this->copyFixture('SharedVirion');
        $source = $project . '/src/Shared';
        $outside = $this->temporaryDirectory . '/external-source';
        rename($source, $outside);
        $this->createSymbolicLink($outside, $source);

        $this->expectException(VirionException::class);
        $this->expectExceptionMessage('symbolic link');
        $this->factory()->open($project);
    }

    private function factory(): VirionProjectFactory
    {
        return new VirionProjectFactory(new VirionManifestReader(new YamlReader()));
    }
}
