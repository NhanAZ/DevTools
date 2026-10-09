<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Tests\Build;

use NhanAZ\DevTools\Build\BuildException;
use NhanAZ\DevTools\Build\NamespaceShader;
use NhanAZ\DevTools\Tests\TestCase;

final class NamespaceShaderTest extends TestCase
{
    public function test_static_php_name_forms_are_shaded_by_ast(): void
    {
        $source = <<<'PHP'
<?php
namespace Fixture;
use Shared\Virion\Greeting;
use function Shared\Virion\helper;
use const Shared\Virion\VALUE;
#[\Shared\Virion\Marker]
final class Example extends \Shared\Virion\BaseType {
    use \Shared\Virion\Helpful;
    public function value(\Shared\Virion\Contract $value): Greeting {
        return \Shared\Virion\Greeting::make($value);
    }
}
PHP;
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'Example.php', $source);

        (new NamespaceShader())->shadeDirectory($directory, ['Shared\Virion' => 'Fixture\Shade\Shared']);

        $result = file_get_contents($directory . DIRECTORY_SEPARATOR . 'Example.php');
        self::assertIsString($result);
        self::assertStringNotContainsString('Shared\Virion', $result);
        self::assertGreaterThanOrEqual(8, substr_count($result, 'Fixture\Shade\Shared'));
    }

    public function test_dynamic_class_string_is_rejected(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'Unsafe.php', "<?php\n\$class = 'Shared\\\\Virion\\\\Greeting';\n");

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('string literal');

        (new NamespaceShader())->shadeDirectory($directory, ['Shared\Virion' => 'Fixture\Shade\Shared']);
    }

    public function test_shaded_namespace_with_same_text_prefix_is_not_rejected(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        $path = $directory . DIRECTORY_SEPARATOR . 'Example.php';
        file_put_contents(
            $path,
            "<?php\nnamespace NhanAZ\\FixtureLibraryExample;\nuse NhanAZ\\FixtureLibrary\\FixtureLibrary;\nfinal class Example { private const LABEL = 'NhanAZ\\\\FixtureLibraryExample'; }\n",
        );
        $shader = new NamespaceShader();
        $shader->shadeDirectory(
            $directory,
            ['NhanAZ\FixtureLibrary' => 'NhanAZ\FixtureLibraryExample\_DevTools\FixtureLibrary_fixture'],
        );
        $shader->assertNoNamespaceReferences($directory, ['NhanAZ\FixtureLibrary']);

        $result = file_get_contents($path);
        self::assertIsString($result);
        self::assertStringContainsString('NhanAZ\FixtureLibraryExample\_DevTools\FixtureLibrary_fixture', $result);
        self::assertStringContainsString('NhanAZ\\FixtureLibraryExample', $result);
        self::assertStringNotContainsString('use NhanAZ\FixtureLibrary\FixtureLibrary;', $result);
    }

    public function test_split_dynamic_class_string_is_rejected(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'Unsafe.php', "<?php\n\$class = 'Shared\\\\' . 'Virion\\\\Greeting';\n");

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('string concatenation');

        (new NamespaceShader())->shadeDirectory($directory, ['Shared\Virion' => 'Fixture\Shade\Shared']);
    }

    public function test_dynamic_static_call_is_rejected(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'Unsafe.php', "<?php\n\$class::run();\n");

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('dynamic class reference');

        (new NamespaceShader())->shadeDirectory($directory, ['Shared\Virion' => 'Fixture\Shade\Shared']);
    }
}
