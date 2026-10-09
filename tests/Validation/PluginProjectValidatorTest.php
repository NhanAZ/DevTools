<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Tests\Validation;

use NhanAZ\DevTools\Tests\TestCase;

final class PluginProjectValidatorTest extends TestCase
{
    public function test_valid_project_has_no_errors(): void
    {
        $project = $this->copyFixture('ValidPlugin');
        [, $validator] = $this->validationServices();

        self::assertFalse($validator->validate($project)->hasErrors());
    }

    /** @dataProvider invalidProjectProvider */
    public function test_invalid_projects_produce_actionable_codes(string $fixture, string $expectedCode): void
    {
        $project = $this->copyFixture($fixture);
        [, $validator] = $this->validationServices();

        $result = $validator->validate($project);

        self::assertTrue($result->hasErrors());
        self::assertContains($expectedCode, array_map(static fn($issue): string => $issue->code, $result->issues()));
        self::assertNotEmpty($result->issues()[0]->suggestion);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidProjectProvider(): iterable
    {
        yield 'invalid YAML' => ['InvalidManifest', 'plugin.manifest_invalid'];
        yield 'missing required fields' => ['MissingFields', 'plugin.manifest_invalid'];
        yield 'missing main file' => ['MissingMain', 'plugin.main_missing'];
        yield 'namespace mismatch' => ['NamespaceMismatch', 'plugin.main_declaration_mismatch'];
        yield 'duplicate commands' => ['DuplicateManifest', 'plugin.duplicate_commands'];
    }

    public function test_duplicate_permissions_are_reported_independently(): void
    {
        $project = $this->copyFixture('DuplicateManifest');
        [, $validator] = $this->validationServices();

        $codes = array_map(static fn($issue): string => $issue->code, $validator->validate($project)->issues());

        self::assertContains('plugin.duplicate_commands', $codes);
        self::assertContains('plugin.duplicate_permissions', $codes);
    }
}
