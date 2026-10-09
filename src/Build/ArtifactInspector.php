<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Build;

use NhanAZ\DevTools\Validation\PluginManifestReader;
use Phar;

final class ArtifactInspector
{
    public function __construct(private readonly PluginManifestReader $manifestReader) {}

    /** @return array{artifact: string, sha256: string, plugin: array{name: string, version: string}, files: int, signature: string} */
    public function inspect(string $path): array
    {
        $real = realpath($path);
        if ($real === false || !is_file($real)) {
            throw new BuildException("Artifact does not exist: {$path}");
        }
        $phar = new Phar($real);
        $manifest = $this->manifestReader->read('phar://' . str_replace('\\', '/', $real) . '/plugin.yml');
        $main = $manifest->expectedMainRelativePath();
        if ($main === null || !isset($phar['src/' . $main])) {
            throw new BuildException("Artifact is missing its declared main class source: {$path}");
        }
        /** @var array{hash: string, hash_type: string}|false $signature */
        $signature = $phar->getSignature();
        if ($signature === false || $signature['hash_type'] === '') {
            throw new BuildException("Artifact has no PHAR signature: {$path}");
        }
        $hash = hash_file('sha256', $real);
        if ($hash === false) {
            throw new BuildException("Cannot hash artifact: {$path}");
        }

        return [
            'artifact' => $real,
            'sha256' => $hash,
            'plugin' => ['name' => $manifest->description->getName(), 'version' => $manifest->description->getVersion()],
            'files' => count($phar),
            'signature' => $signature['hash_type'],
        ];
    }

}
