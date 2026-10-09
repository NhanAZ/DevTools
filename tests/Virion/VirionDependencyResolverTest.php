<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Tests\Virion;

use NhanAZ\DevTools\Tests\TestCase;
use NhanAZ\DevTools\Virion\VirionDependencyResolver;
use NhanAZ\DevTools\Virion\VirionException;
use NhanAZ\DevTools\Virion\VirionManifest;
use NhanAZ\DevTools\Virion\VirionProject;
use NhanAZ\DevTools\Virion\VirionProjectSelector;
use NhanAZ\DevTools\Virion\VirionRequirement;

final class VirionDependencyResolverTest extends TestCase
{
    public function test_discarded_version_does_not_retain_an_orphan_cycle(): void
    {
        $a1 = $this->project('A', '1.0.0');
        $a2 = $this->project('A', '2.0.0', ['B' => '*']);
        $b = $this->project('B', '1.0.0', ['B' => '*']);
        $c = $this->project('C', '1.0.0', ['A' => '1.0.0']);

        self::assertSame([$a1, $c], $this->resolver()->resolve(['a' => [$a1, $a2], 'b' => [$b], 'c' => [$c]], $this->roots('A', 'C')));
    }

    public function test_missing_dependency_of_discarded_version_does_not_fail_selection(): void
    {
        $a1 = $this->project('A', '1.0.0');
        $a2 = $this->project('A', '2.0.0', ['Missing' => '*']);
        $c = $this->project('C', '1.0.0', ['A' => '1.0.0']);

        self::assertSame([$a1, $c], $this->resolver()->resolve(['a' => [$a1, $a2], 'c' => [$c]], $this->roots('A', 'C')));
    }

    public function test_reachable_compatible_cycle_is_selected_once(): void
    {
        $a = $this->project('A', '1.0.0', ['B' => '*']);
        $b = $this->project('B', '1.0.0', ['A' => '*']);

        self::assertSame([$a, $b], $this->resolver()->resolve(['a' => [$a], 'b' => [$b]], $this->roots('A')));
    }

    public function test_changed_parent_constraint_is_rechecked_before_reporting_missing_compatible_version(): void
    {
        $a1 = $this->project('A', '1.0.0', ['B' => '^1.0.0']);
        $a2 = $this->project('A', '2.0.0', ['B' => '^2.0.0']);
        $b = $this->project('B', '1.0.0');
        $c = $this->project('C', '1.0.0', ['A' => '^1.0.0']);

        self::assertSame([$a1, $c, $b], $this->resolver()->resolve(['a' => [$a1, $a2], 'b' => [$b], 'c' => [$c]], $this->roots('A', 'C')));
    }

    public function test_incompatible_current_parent_constraint_still_fails(): void
    {
        $a = $this->project('A', '1.0.0', ['B' => '^2.0.0']);
        $b = $this->project('B', '1.0.0');
        $this->expectException(VirionException::class);
        $this->expectExceptionMessage('No version of virion B satisfies');
        $this->resolver()->resolve(['a' => [$a], 'b' => [$b]], $this->roots('A'));
    }

    public function test_missing_root_fails_before_returning_empty_graph(): void
    {
        $this->expectException(VirionException::class);
        $this->expectExceptionMessage('Required virion Missing is missing');
        $this->resolver()->resolve([], $this->roots('Missing'));
    }

    public function test_nonconverging_selection_fails_instead_of_retaining_orphan_constraints(): void
    {
        $a1 = $this->project('A', '1.0.0');
        $a2 = $this->project('A', '2.0.0', ['B' => '*']);
        $b = $this->project('B', '1.0.0', ['A' => '1.0.0']);

        $this->expectException(VirionException::class);
        $this->expectExceptionMessage('does not converge');
        $this->resolver()->resolve(['a' => [$a1, $a2], 'b' => [$b]], $this->roots('A'));
    }

    private function resolver(): VirionDependencyResolver
    {
        return new VirionDependencyResolver(new VirionProjectSelector());
    }

    /** @return list<VirionRequirement> */
    private function roots(string ...$names): array
    {
        return array_map(static fn(string $name): VirionRequirement => new VirionRequirement($name, '*', 'plugin.yml'), array_values($names));
    }

    /** @param array<string, string> $dependencies */
    private function project(string $name, string $version, array $dependencies = []): VirionProject
    {
        $path = $this->temporaryDirectory . '/' . $name . '/' . $version;
        $requirements = [];
        foreach ($dependencies as $dependency => $constraint) {
            $requirements[] = new VirionRequirement($dependency, $constraint, $path . '/virion.yml');
        }

        return new VirionProject($path, $path . '/src', new VirionManifest($name, $version, $name, [], ['8.1'], [], $path . '/virion.yml', $requirements), false);
    }
}
