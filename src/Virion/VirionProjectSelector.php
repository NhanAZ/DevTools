<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Virion;

use function array_filter;
use function array_map;
use function count;
use function implode;
use function usort;
use function version_compare;

final class VirionProjectSelector
{
    /**
     * @param list<VirionProject> $projects
     * @param list<VirionRequirement> $requirements
     */
    public function select(string $name, array $projects, array $requirements): VirionProject
    {
        $compatible = array_values(array_filter(
            $projects,
            static function (VirionProject $project) use ($requirements): bool {
                foreach ($requirements as $requirement) {
                    if (!$requirement->matches($project->manifest->version)) {
                        return false;
                    }
                }

                return true;
            },
        ));
        if ($compatible === []) {
            $available = implode(', ', array_map(
                static fn(VirionProject $project): string => $project->manifest->version . ' at ' . $project->location,
                $projects,
            ));
            $requested = $this->describeRequirements($requirements);
            throw new VirionException(
                "No version of virion {$name} satisfies {$requested}. Available: {$available}.",
            );
        }

        usort(
            $compatible,
            static fn(VirionProject $left, VirionProject $right): int => version_compare(
                $right->manifest->version,
                $left->manifest->version,
            ),
        );
        $selected = $compatible[0];
        $sameVersion = array_filter(
            $compatible,
            static fn(VirionProject $project): bool => version_compare(
                $project->manifest->version,
                $selected->manifest->version,
                '=',
            ),
        );
        if (count($sameVersion) > 1) {
            $locations = implode(', ', array_map(
                static fn(VirionProject $project): string => $project->location,
                $sameVersion,
            ));
            throw new VirionException(
                "Virion {$name} version {$selected->manifest->version} exists in multiple locations: {$locations}. Remove the duplicate copy.",
            );
        }

        return $selected;
    }

    /** @param list<VirionRequirement> $requirements */
    public function describeRequirements(array $requirements): string
    {
        if ($requirements === []) {
            return 'the implicit * constraint';
        }

        return implode(' and ', array_map(
            static fn(VirionRequirement $requirement): string => $requirement->constraint->expression . ' from ' . $requirement->source,
            $requirements,
        ));
    }
}
