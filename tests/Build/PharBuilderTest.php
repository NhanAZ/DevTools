<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Tests\Build;

use NhanAZ\DevTools\Build\BuildException;
use NhanAZ\DevTools\Tests\TestCase;
use Phar;
use PharFileInfo;

final class PharBuilderTest extends TestCase
{
    public function test_documented_shared_virion_example_builds_as_a_standalone_plugin(): void
    {
        $examples = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'examples';
        $project = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'HelloShared';
        $virion = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'virions' . DIRECTORY_SEPARATOR . 'SharedGreeting';
        $this->filesystem->copyTree($examples . DIRECTORY_SEPARATOR . 'HelloShared', $project);
        $this->filesystem->copyTree($examples . DIRECTORY_SEPARATOR . 'SharedGreeting', $virion);

        $result = $this->builder()->build(
            $project,
            dirname($virion),
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'build',
        );

        $phar = new Phar($result->outputPath);
        self::assertSame('HelloShared', $result->pluginName);
        self::assertCount(1, $result->shadedVirions);
        self::assertStringNotContainsString(
            'DevToolsExample\\SharedGreeting',
            $phar['src/Main.php']->getContent(),
        );
        self::assertTrue(isset($phar['META-INF/virions/SharedGreeting/LICENSE']));
    }

    public function test_build_contains_resources_and_shaded_virion(): void
    {
        $project = $this->copyFixture('BuildPlugin', 'plugins/BuildPlugin');
        $virions = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'virions';
        $this->copyFixture('SharedVirion', 'virions/SharedVirion');
        $output = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'build';
        $sourceBefore = file_get_contents($project . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Main.php');

        $result = $this->builder()->build($project, $virions, $output);

        self::assertFileExists($result->outputPath);
        self::assertCount(1, $result->shadedVirions);
        $target = $result->shadedVirions['Shared\Virion'];
        $phar = new Phar($result->outputPath);
        self::assertTrue(isset($phar['plugin.yml']));
        self::assertTrue(isset($phar['LICENSE']));
        self::assertSame('resource included', trim($phar['resources/message.txt']->getContent()));
        self::assertSame('shared virion resource', trim($phar['resources/devtools-virions/SharedVirion/default.txt']->getContent()));
        self::assertTrue(isset($phar['META-INF/virions/SharedVirion/LICENSE']));
        self::assertStringContainsString($target, $phar['src/Main.php']->getContent());
        $virionPath = 'src/' . str_replace('Fixture/Build/', '', str_replace('\\', '/', $target)) . '/Greeting.php';
        self::assertTrue(isset($phar[$virionPath]), $virionPath);
        self::assertStringNotContainsString('namespace Shared\Virion', $phar[$virionPath]->getContent());
        require_once 'phar://' . str_replace('\\', '/', $result->outputPath) . '/' . $virionPath;
        self::assertSame('shared', ($target . '\\Greeting')::message());
        self::assertSame($sourceBefore, file_get_contents($project . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Main.php'));

        $allPhp = '';
        foreach (new \RecursiveIteratorIterator($phar) as $entry) {
            if (!$entry instanceof PharFileInfo) {
                continue;
            }
            if ($entry->isFile() && str_ends_with(strtolower($entry->getFilename()), '.php')) {
                $allPhp .= $entry->getContent();
            }
        }
        self::assertStringNotContainsString('Shared\Virion', $allPhp);
        self::assertStringNotContainsString(str_replace('\\', '/', $virions), $allPhp);
    }

    public function test_existing_output_is_protected(): void
    {
        $project = $this->copyFixture('BuildPlugin', 'plugins/BuildPlugin');
        $virions = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'virions';
        $this->copyFixture('SharedVirion', 'virions/SharedVirion');
        $output = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'build';
        $builder = $this->builder();
        $first = $builder->build($project, $virions, $output);
        $hash = hash_file('sha256', $first->outputPath);

        try {
            $builder->build($project, $virions, $output);
            self::fail('Expected existing output protection.');
        } catch (BuildException $error) {
            self::assertStringContainsString('Refusing to replace', $error->getMessage());
        }
        self::assertSame($hash, hash_file('sha256', $first->outputPath));
    }

    public function test_explicit_overwrite_replaces_completed_output_and_cleans_backups(): void
    {
        $project = $this->copyFixture('BuildPlugin', 'plugins/BuildPlugin');
        $virions = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'virions';
        $this->copyFixture('SharedVirion', 'virions/SharedVirion');
        $output = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'build';
        $builder = $this->builder();
        $first = $builder->build($project, $virions, $output);
        $firstHash = hash_file('sha256', $first->outputPath);
        file_put_contents($project . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'message.txt', 'updated resource');

        $second = $builder->build($project, $virions, $output, true);

        self::assertNotSame($firstHash, hash_file('sha256', $second->outputPath));
        $phar = new Phar($second->outputPath);
        self::assertSame('updated resource', $phar['resources/message.txt']->getContent());
        unset($phar);
        self::assertSame([], glob($output . DIRECTORY_SEPARATOR . '*.backup-*') ?: []);
        self::assertSame([], glob($output . DIRECTORY_SEPARATOR . '.devtools-stage-*') ?: []);
    }

    public function test_output_inside_source_is_rejected(): void
    {
        $project = $this->copyFixture('BuildPlugin', 'plugins/BuildPlugin');
        $virions = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'virions';
        $this->copyFixture('SharedVirion', 'virions/SharedVirion');

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('inside plugin source');

        $this->builder()->build($project, $virions, $project . DIRECTORY_SEPARATOR . 'src');
    }

    public function test_include_path_may_not_overlap_build_output(): void
    {
        $project = $this->copyFixture('BuildPlugin', 'plugins/BuildPlugin');
        $virions = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'virions';
        $this->copyFixture('SharedVirion', 'virions/SharedVirion');
        file_put_contents(
            $project . DIRECTORY_SEPARATOR . 'devtools.yml',
            "virions:\n  - SharedVirion\ninclude-paths:\n  - build\n",
        );

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('overlaps the build output');

        $this->builder()->build($project, $virions, $project . DIRECTORY_SEPARATOR . 'build');
    }

    public function test_unknown_build_config_key_is_rejected(): void
    {
        $project = $this->copyFixture('BuildPlugin', 'plugins/BuildPlugin');
        file_put_contents($project . DIRECTORY_SEPARATOR . 'devtools.yml', "virons:\n  - SharedVirion\n");

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('Unknown key "virons"');

        $this->builder()->build(
            $project,
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'virions',
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'build',
        );
    }

    public function test_sensitive_declared_include_path_is_rejected(): void
    {
        $project = $this->copyFixture('BuildPlugin', 'plugins/BuildPlugin');
        file_put_contents($project . DIRECTORY_SEPARATOR . '.env.production', 'TOKEN=secret');
        file_put_contents($project . DIRECTORY_SEPARATOR . 'devtools.yml', "include-paths:\n  - .env.production\n");

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('Refusing sensitive include path');

        $this->builder()->build(
            $project,
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'virions',
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'build',
        );
    }

    public function test_duplicate_virion_names_are_ambiguous(): void
    {
        $project = $this->copyFixture('BuildPlugin', 'plugins/BuildPlugin');
        $virions = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'virions';
        $this->copyFixture('SharedVirion', 'virions/SharedVirion-A');
        $this->copyFixture('SharedVirion', 'virions/SharedVirion-B');

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('exists in multiple locations');

        $this->builder()->build($project, $virions, $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'build');
    }

    public function test_versioned_virion_requirement_selects_highest_compatible_version(): void
    {
        $project = $this->copyFixture('BuildPlugin', 'plugins/BuildPlugin');
        file_put_contents(
            $project . DIRECTORY_SEPARATOR . 'devtools.yml',
            "virions:\n  - name: SharedVirion\n    version: ^1.0.0\n",
        );
        $virions = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'virions';
        $versionOne = $this->copyFixture('SharedVirion', 'virions/SharedVirion-v1');
        $versionTwo = $this->copyFixture('SharedVirion', 'virions/SharedVirion-v2');
        file_put_contents(
            $versionOne . DIRECTORY_SEPARATOR . 'virion.yml',
            "name: SharedVirion\nversion: 1.7.0\nantigen: Shared\\Virion\napi: 5.0.0\n",
        );
        file_put_contents(
            $versionTwo . DIRECTORY_SEPARATOR . 'virion.yml',
            "name: SharedVirion\nversion: 2.0.0\nantigen: Shared\\Virion\napi: 5.0.0\n",
        );
        $greeting = $versionOne . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Shared'
            . DIRECTORY_SEPARATOR . 'Virion' . DIRECTORY_SEPARATOR . 'Greeting.php';
        $source = file_get_contents($greeting);
        self::assertIsString($source);
        file_put_contents($greeting, str_replace("return 'shared';", "return 'selected-1.7';", $source));

        $result = $this->builder()->build(
            $project,
            $virions,
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'build',
        );
        $phar = new Phar($result->outputPath);
        $virionSource = '';
        foreach (new \RecursiveIteratorIterator($phar) as $entry) {
            if ($entry instanceof PharFileInfo && $entry->getFilename() === 'Greeting.php') {
                $virionSource .= $entry->getContent();
            }
        }

        self::assertStringContainsString('selected-1.7', $virionSource);
    }

    public function test_standalone_build_rejects_devtools_dependency(): void
    {
        $project = $this->copyFixture('BuildPlugin', 'plugins/BuildPlugin');
        $virions = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'virions';
        $this->copyFixture('SharedVirion', 'virions/SharedVirion');
        file_put_contents($project . DIRECTORY_SEPARATOR . 'plugin.yml', "depend: [DevTools]\n", FILE_APPEND);

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('cannot retain a DevTools');

        $this->builder()->build($project, $virions, $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'build');
    }

    public function test_available_but_undeclared_virion_reference_is_rejected(): void
    {
        $project = $this->copyFixture('BuildPlugin', 'plugins/BuildPlugin');
        $virions = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'virions';
        $this->copyFixture('SharedVirion', 'virions/SharedVirion');
        file_put_contents($project . DIRECTORY_SEPARATOR . 'devtools.yml', "virions: []\n");

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('development-only namespace');

        $this->builder()->build($project, $virions, $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'build');
    }

    public function test_devtools_self_build_ignores_unrelated_available_virions(): void
    {
        $project = $this->copyFixture('ValidPlugin', 'plugins/DevTools');
        $this->addDynamicDependencyInclude($project);
        $manifestPath = $project . DIRECTORY_SEPARATOR . 'plugin.yml';
        $manifest = file_get_contents($manifestPath);
        self::assertIsString($manifest);
        file_put_contents($manifestPath, str_replace('name: FixturePlugin', 'name: DevTools', $manifest));
        $virion = $this->copyFixture('SharedVirion', 'virions/SharedVirion');

        $result = $this->builder()->build(
            $project,
            dirname($virion),
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'build',
        );

        self::assertSame([], $result->shadedVirions);
        self::assertFileExists($result->outputPath);
    }

    public function test_non_devtools_build_still_rejects_unproven_dynamic_class_reference(): void
    {
        $project = $this->copyFixture('ValidPlugin', 'plugins/RegularPlugin');
        $this->addDynamicDependencyInclude($project);
        $virion = $this->copyFixture('SharedVirion', 'virions/SharedVirion');

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('dynamic class reference');

        $this->builder()->build(
            $project,
            dirname($virion),
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'build',
        );
    }

    public function test_direct_devtools_code_dependency_is_rejected(): void
    {
        $project = $this->copyFixture('BuildPlugin', 'plugins/BuildPlugin');
        $virions = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'virions';
        $this->copyFixture('SharedVirion', 'virions/SharedVirion');
        $mainPath = $project . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Main.php';
        $source = file_get_contents($mainPath);
        self::assertIsString($source);
        file_put_contents(
            $mainPath,
            str_replace(
                'use Shared\\Virion\\Greeting;',
                "use NhanAZ\\DevTools\\DevTools;\nuse Shared\\Virion\\Greeting;",
                $source,
            ),
        );

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('development-only namespace nhanaz\\devtools');

        $this->builder()->build($project, $virions, $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'build');
    }

    public function test_overlapping_declared_antigens_are_rejected(): void
    {
        $project = $this->copyFixture('BuildPlugin', 'plugins/BuildPlugin');
        $virions = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'virions';
        $this->copyFixture('SharedVirion', 'virions/SharedVirion');
        $nested = $this->copyFixture('SharedVirion', 'virions/NestedVirion');
        file_put_contents(
            $nested . DIRECTORY_SEPARATOR . 'virion.yml',
            "name: NestedVirion\nversion: 1.0.0\nantigen: Shared\\Virion\\Nested\napi: 5.0.0\n",
        );
        file_put_contents(
            $project . DIRECTORY_SEPARATOR . 'devtools.yml',
            "virions:\n  - SharedVirion\n  - NestedVirion\n",
        );

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('overlaps declared antigen');

        $this->builder()->build($project, $virions, $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'build');
    }

    private function addDynamicDependencyInclude(string $project): void
    {
        $dependency = $project . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'DynamicFactory.php';
        $this->filesystem->ensureDirectory(dirname($dependency));
        file_put_contents(
            $dependency,
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Fixture\BuildDependency;

final class DynamicFactory
{
    public static function create(): object
    {
        $className = \stdClass::class;

        return new $className();
    }
}
PHP,
        );
        file_put_contents(
            $project . DIRECTORY_SEPARATOR . 'devtools.yml',
            "include-paths:\n  - vendor/DynamicFactory.php\n",
        );
    }
}
