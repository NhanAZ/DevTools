<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Build;

use NhanAZ\DevTools\Support\Filesystem;
use NhanAZ\DevTools\Support\Path;
use NhanAZ\DevTools\Support\PhpSourceInspector;
use NhanAZ\DevTools\Support\YamlReader;
use NhanAZ\DevTools\Virion\VirionClassScanner;
use NhanAZ\DevTools\Virion\VirionManifestReader;
use NhanAZ\DevTools\Virion\VirionPlatformRequirements;
use NhanAZ\DevTools\Virion\VirionProjectFactory;
use NhanAZ\DevTools\Virion\VirionSourceFingerprint;

final class ComposerVirionPreparer
{
    public function __construct(private readonly Filesystem $filesystem) {}

    /** @return list<array<string, mixed>> */
    public function prepare(string $projectRoot, string $destination): array
    {
        $plan = new ComposerVirionPlan();
        $packages = $plan->packages($projectRoot);
        if ($packages === []) {
            return [];
        }
        $projectRoot = realpath($projectRoot) ?: throw new BuildException('Cannot resolve project root.');
        $destination = rtrim($destination, '/\\');
        if (file_exists($destination) || is_link($destination)) {
            throw new BuildException("Refusing to replace existing dependency directory {$destination}. Prepare into a new directory, then select it with --virions.");
        }
        for ($component = dirname($destination); dirname($component) !== $component; $component = dirname($component)) {
            if (is_link($component)) {
                throw new BuildException("Refusing dependency output through symbolic link {$component}.");
            }
        }
        $this->filesystem->ensureDirectory(dirname($destination));
        $parent = realpath(dirname($destination)) ?: throw new BuildException('Cannot resolve dependency output parent.');
        if (Path::isInside($projectRoot . '/vendor', $parent) || Path::isInside($projectRoot . '/src', $parent)) {
            throw new BuildException('Dependency output must be outside vendor/ and src/.');
        }
        $composer = $plan->readJson($projectRoot . '/composer.json');
        $config = $composer['config'] ?? [];
        if (is_array($config) && isset($config['vendor-dir']) && $config['vendor-dir'] !== 'vendor') {
            throw new BuildException('Composer preparation currently requires the standard vendor directory.');
        }
        $this->filesystem->assertNoSymbolicLinkComponents($projectRoot, 'vendor/composer/installed.json');
        $installed = $plan->readJson($projectRoot . '/vendor/composer/installed.json');
        $installedByName = [];
        $installedPackages = $installed['packages'] ?? [];
        if (!is_array($installedPackages)) {
            throw new BuildException('Invalid vendor/composer/installed.json packages list.');
        }
        foreach ($installedPackages as $package) {
            if (is_array($package) && is_string($package['name'] ?? null)) {
                $installedByName[$package['name']] = $package;
            }
        }
        $stage = $parent . '/.devtools-prepare-' . bin2hex(random_bytes(8));
        $this->filesystem->ensureDirectory($stage);
        $result = [];
        try {
            $factory = new VirionProjectFactory(new VirionManifestReader(new YamlReader()));
            $scanner = new VirionClassScanner(new PhpSourceInspector());
            foreach ($packages as $name => $package) {
                $installedPackage = $installedByName[$name] ?? null;
                foreach (['version', 'source', 'dist', 'autoload', 'require', 'extra'] as $field) {
                    if (!$plan->sameMetadata($installedPackage[$field] ?? null, $package[$field] ?? null)) {
                        throw new BuildException("Installed Composer package {$name} differs from composer.lock ({$field}). Run composer install --no-dev --no-scripts --no-plugins again.");
                    }
                }
                $relative = 'vendor/' . $name;
                $this->filesystem->assertNoSymbolicLinkComponents($projectRoot, $relative . '/composer.json');
                $source = $projectRoot . '/' . $relative;
                $sourceComposer = $plan->readJson($source . '/composer.json');
                foreach (['name', 'autoload', 'require', 'extra'] as $field) {
                    if (!$plan->sameMetadata($sourceComposer[$field] ?? null, $package[$field] ?? null)) {
                        throw new BuildException("Installed {$name}/composer.json differs from composer.lock ({$field}). Refusing changed package metadata.");
                    }
                }
                $antigen = $plan->antigen($package);
                $sourceRelative = Path::normalizeRelative($plan->sourceDirectory($package));
                $this->filesystem->assertNoSymbolicLinkComponents($source, $sourceRelative);
                if (is_dir($source . '/resources')) {
                    throw new BuildException("Composer virion {$name} has root resources/. Only resources colocated under its PSR-4 source directory retain their relative paths when bundled.");
                }
                $localName = ComposerVirionPlan::localName($name);
                $target = $stage . '/' . $localName;
                if (file_exists($target)) {
                    throw new BuildException("Composer package name collision at {$localName}.");
                }
                $this->filesystem->copyTree($source . '/' . $sourceRelative, $target . '/src', static function (string $relative): bool {
                    if (Path::isSensitive($relative)) {
                        throw new BuildException("Refusing sensitive package source file {$relative}.");
                    }
                    return !Path::isHiddenOrTemporary($relative);
                });
                $requirements = [];
                foreach ($plan->dependencyNames($package['require'] ?? [], $name) as $dependency) {
                    if ($dependency === 'pocketmine/pocketmine-mp' && !isset($packages[$dependency])) {
                        continue;
                    }
                    $requirements[] = ['name' => ComposerVirionPlan::localName($dependency), 'version' => $plan->version($packages[$dependency])];
                }
                $manifest = ['name' => $localName, 'version' => $plan->version($package), 'antigen' => $antigen, 'php' => ['8.1'], 'virions' => $requirements];
                $platform = [];
                foreach ($plan->object($package['require'] ?? [], $name) as $dependency => $constraint) {
                    if (!str_contains($dependency, '/') && !str_starts_with($dependency, 'composer-')) {
                        $platform[$dependency] = $constraint;
                    }
                }
                (new VirionPlatformRequirements())->validate($platform, $name);
                $manifest['composer-platform'] = $platform;
                $this->write($target . '/virion.yml', yaml_emit($manifest));
                foreach (['LICENSE', 'LICENSE.md', 'LICENSE.txt', 'COPYING', 'COPYING.md', 'NOTICE', 'NOTICE.md', 'THIRD_PARTY_NOTICES.md'] as $license) {
                    if (is_file($source . '/' . $license)) {
                        $this->filesystem->copyFile($source . '/' . $license, $target . '/' . $license);
                    }
                }
                $metadata = ['package' => $name, 'name' => $localName, 'version' => $manifest['version'], 'antigen' => $antigen, 'source' => $package['source'] ?? null, 'dist' => $package['dist'] ?? null, 'composerLockSha256' => hash_file('sha256', $projectRoot . '/composer.lock')];
                $metadata['sourceSha256'] = (new VirionSourceFingerprint())->hash($target . '/src');
                $metadata['manifestSha256'] = hash_file('sha256', $target . '/virion.yml');
                $metadata['composerPackageSha256'] = $plan->fingerprint($package);
                $this->write($target . '/devtools-provenance.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
                $project = $factory->open($target);
                $classes = $scanner->scan($project);
                if ($classes === []) {
                    throw new BuildException("Composer virion {$name} contains no autoloadable classes.");
                }
                foreach ($classes as $class) {
                    if (!str_starts_with($class, $antigen . '\\')) {
                        throw new BuildException("Composer virion {$name} declares {$class} outside {$antigen}.");
                    }
                }
                $result[] = $metadata;
            }
            $this->write($stage . '/devtools-prepared.json', json_encode(['schemaVersion' => 1, 'packages' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            if (!rename($stage, $destination)) {
                throw new BuildException("Cannot install prepared dependencies at {$destination}.");
            }
        } finally {
            $this->filesystem->removeTree($stage);
        }
        return $result;
    }

    private function write(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new BuildException("Cannot write prepared dependency metadata {$path}.");
        }
    }
}
