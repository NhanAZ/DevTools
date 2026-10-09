<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Tests\Virion;

use NhanAZ\DevTools\Virion\VirionException;
use NhanAZ\DevTools\Virion\VirionVersionConstraint;
use PHPUnit\Framework\TestCase;

final class VirionVersionConstraintTest extends TestCase
{
    /** @dataProvider matchingConstraintProvider */
    public function test_supported_constraints_match_expected_versions(
        string $constraint,
        string $version,
        bool $expected,
    ): void {
        self::assertSame($expected, (new VirionVersionConstraint($constraint))->matches($version));
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function matchingConstraintProvider(): iterable
    {
        yield 'any version' => ['*', '9.4.1', true];
        yield 'exact match' => ['1.2.3', '1.2.3', true];
        yield 'exact mismatch' => ['1.2.3', '1.2.4', false];
        yield 'caret stable major' => ['^1.2.3', '1.9.0', true];
        yield 'caret stable next major' => ['^1.2.3', '2.0.0', false];
        yield 'caret zero minor' => ['^0.2.3', '0.2.9', true];
        yield 'caret zero next minor' => ['^0.2.3', '0.3.0', false];
        yield 'tilde same minor' => ['~1.2.3', '1.2.9', true];
        yield 'tilde next minor' => ['~1.2.3', '1.3.0', false];
        yield 'minor wildcard' => ['1.2.*', '1.2.99', true];
        yield 'major wildcard' => ['1.*', '1.99.0', true];
        yield 'comparison range' => ['>=1.2.0 <2.0.0', '1.8.4', true];
        yield 'comparison range upper bound' => ['>=1.2.0 <2.0.0', '2.0.0', false];
    }

    public function test_unsupported_constraint_is_rejected_with_guidance(): void
    {
        $this->expectException(VirionException::class);
        $this->expectExceptionMessage('Use *, an exact version');

        new VirionVersionConstraint('^1.2');
    }
}
