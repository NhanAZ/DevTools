<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Virion;

use function is_array;
use function is_scalar;

use NhanAZ\DevTools\Support\YamlReader;

use function preg_match;

use Throwable;

use function trim;

final class VirionManifestReader
{
    public function __construct(private readonly YamlReader $yamlReader) {}

    public function read(string $path): VirionManifest
    {
        try {
            $raw = $this->yamlReader->read($path);
        } catch (Throwable $error) {
            throw new VirionException("Cannot parse virion manifest {$path}: {$error->getMessage()}", 0, $error);
        }

        foreach (['name', 'version', 'antigen'] as $field) {
            if (!isset($raw[$field]) || !is_scalar($raw[$field]) || trim((string) $raw[$field]) === '') {
                throw new VirionException("Cannot load virion from {$path}: required field \"{$field}\" is missing or empty.");
            }
        }
        $name = trim((string) $raw['name']);
        if (preg_match('/^[A-Za-z0-9_.-]+$/D', $name) !== 1) {
            throw new VirionException("Cannot load virion {$name}: name may contain only letters, numbers, dot, underscore, and hyphen.");
        }
        $antigen = trim((string) $raw['antigen'], " \t\n\r\0\x0B\\");
        if (preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*[A-Za-z_][A-Za-z0-9_]*$/D', $antigen) !== 1) {
            throw new VirionException("Cannot load virion {$name}: antigen \"{$antigen}\" is not a valid namespace.");
        }
        $version = trim((string) $raw['version']);
        if (!VirionVersionConstraint::isValidVersion($version)) {
            throw new VirionException(
                "Cannot load virion {$name}: version \"{$version}\" is not a valid semantic version such as 1.2.3 or 1.2.3-beta.1.",
            );
        }

        $api = $this->versionList($raw['api'] ?? [], 'api', $name);
        $php = $this->versionList($raw['php'] ?? [], 'php', $name);
        if ($api === [] && $php === []) {
            throw new VirionException("Cannot load virion {$name}: virion.yml must declare api or php compatibility.");
        }
        foreach ($api as $apiVersion) {
            if (preg_match('/^\d+\.\d+\.\d+(?:-[A-Za-z0-9.]+)?$/D', $apiVersion) !== 1) {
                throw new VirionException("Cannot load virion {$name}: api contains invalid version \"{$apiVersion}\".");
            }
        }
        foreach ($php as $phpVersion) {
            if (preg_match('/^\d+\.\d+(?:\.\d+)?$/D', $phpVersion) !== 1) {
                throw new VirionException("Cannot load virion {$name}: php contains invalid version \"{$phpVersion}\". Use a version such as 8.1.");
            }
        }

        $requirements = [];
        $declared = $raw['virions'] ?? [];
        if (!is_array($declared) || !array_is_list($declared)) {
            throw new VirionException("Virion dependencies in {$path}:virions must be a list of names or {name, version} maps.");
        }
        foreach ($declared as $entry) {
            if (is_string($entry)) {
                $requirements[] = new VirionRequirement($entry, '*', $path);
                continue;
            }
            if (!is_array($entry) || !is_string($entry['name'] ?? null) || !is_string($entry['version'] ?? '*')
                || array_diff(array_keys($entry), ['name', 'version']) !== []) {
                throw new VirionException("Invalid virion requirement in {$path}:virions. Use a name or {name, version} map.");
            }
            $requirements[] = new VirionRequirement($entry['name'], $entry['version'] ?? '*', $path);
        }

        return new VirionManifest($name, $version, $antigen, $api, $php, $raw, $path, $requirements);
    }

    /** @return list<string> */
    private function versionList(mixed $value, string $field, string $name): array
    {
        if ($value === null || $value === []) {
            return [];
        }
        if (!is_array($value)) {
            $value = [$value];
        }

        $versions = [];
        foreach ($value as $version) {
            if (!is_scalar($version) || trim((string) $version) === '') {
                throw new VirionException("Cannot load virion {$name}: {$field} compatibility must contain only version strings.");
            }
            $versions[] = trim((string) $version);
        }

        return $versions;
    }
}
