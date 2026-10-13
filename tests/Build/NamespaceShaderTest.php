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

    public function test_dynamic_is_a_target_type_is_allowed_with_default_non_autoloading_mode(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        $path = $directory . DIRECTORY_SEPARATOR . 'Matcher.php';
        file_put_contents($path, <<<'PHP'
<?php
namespace Fixture;

use function is_a;

final class Matcher
{
    public function matches(object $throwable, string $class): bool
    {
        return is_a($throwable, $class);
    }
}
PHP);

        $shader = new NamespaceShader();
        $shader->shadeDirectory($directory, ['Shared\Virion' => 'Fixture\Shade\Shared']);
        $shader->assertNoNamespaceReferences($directory, ['Shared\Virion']);

        $result = file_get_contents($path);
        self::assertIsString($result);
        self::assertStringContainsString('is_a($throwable, $class)', $result);

        $autoloaded = [];
        $autoload = static function (string $className) use (&$autoloaded): void {
            $autoloaded[] = $className;
        };
        spl_autoload_register($autoload);
        try {
            self::assertFalse(\is_a(new \stdClass(), 'Fixture\\MissingTarget'));
        } finally {
            spl_autoload_unregister($autoload);
        }
        self::assertSame([], $autoloaded);
    }

    public function test_dynamic_instanceof_target_variable_is_allowed_without_autoloading(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        $path = $directory . DIRECTORY_SEPARATOR . 'Matcher.php';
        file_put_contents($path, <<<'PHP'
<?php
namespace Fixture;

final class Matcher
{
    public function matches(object $value, string $className): bool
    {
        return $value instanceof $className;
    }
}
PHP);

        $shader = new NamespaceShader();
        $shader->shadeDirectory($directory, ['Shared\\Virion' => 'Fixture\\Shade\\Shared']);
        $shader->assertNoNamespaceReferences($directory, ['Shared\\Virion']);

        $result = file_get_contents($path);
        self::assertIsString($result);
        self::assertStringContainsString('$value instanceof $className', $result);

        require_once $path;
        $autoloaded = [];
        $autoload = static function (string $className) use (&$autoloaded): void {
            $autoloaded[] = $className;
        };
        spl_autoload_register($autoload);
        try {
            $matcherClass = $this->runtimeFixtureClass('Matcher');
            $matcher = (new \ReflectionClass($matcherClass))->newInstance();
            $matches = new \ReflectionMethod($matcher, 'matches');
            self::assertFalse($matches->invoke($matcher, new \stdClass(), 'Fixture\\MissingTarget'));
        } finally {
            spl_autoload_unregister($autoload);
        }
        self::assertSame([], $autoloaded);
    }

    public function test_computed_instanceof_target_remains_rejected(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'UnsafeMatcher.php', <<<'PHP'
<?php
namespace Fixture;

final class UnsafeMatcher
{
    public function matches(object $value, string $className, string $suffix): bool
    {
        return $value instanceof ($className . $suffix);
    }
}
PHP);

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('dynamic class reference');

        (new NamespaceShader())->shadeDirectory($directory, ['Shared\\Virion' => 'Fixture\\Shade\\Shared']);
    }

    public function test_dynamic_is_a_target_type_with_autoload_enabled_is_rejected(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'Matcher.php', <<<'PHP'
<?php
namespace Fixture;

final class Matcher
{
    public function matches(mixed $throwable, string $class): bool
    {
        return is_a($throwable, $class, true);
    }
}
PHP);

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('dynamic class-name argument to is_a()');

        (new NamespaceShader())->shadeDirectory($directory, ['Shared\Virion' => 'Fixture\Shade\Shared']);
    }

    public function test_dynamic_target_to_namespaced_is_a_function_is_rejected(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'Matcher.php', <<<'PHP'
<?php
namespace Fixture;

function is_a(mixed $objectOrClass, string $class): bool
{
    return true;
}

final class Matcher
{
    public function matches(mixed $throwable, string $class): bool
    {
        return is_a($throwable, $class);
    }
}
PHP);

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('dynamic class-name argument to is_a()');

        (new NamespaceShader())->shadeDirectory($directory, ['Shared\Virion' => 'Fixture\Shade\Shared']);
    }

    public function test_current_fiber_suspend_is_rewritten_to_the_native_static_call(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        $path = $directory . DIRECTORY_SEPARATOR . 'GreenThread.php';
        file_put_contents($path, <<<'PHP'
<?php
namespace Fixture;

use Fiber;

final class GreenThread
{
    public function suspendCurrent(): void
    {
        $fiberCurrent = Fiber::getCurrent();
        if ($fiberCurrent !== null) {
            $fiberCurrent::suspend();
        }
    }
}
PHP);

        $shader = new NamespaceShader();
        $shader->shadeDirectory($directory, ['Shared\\Virion' => 'Fixture\\Shade\\Shared']);
        $shader->assertNoNamespaceReferences($directory, ['Shared\\Virion']);

        $result = file_get_contents($path);
        self::assertIsString($result);
        self::assertStringContainsString('\Fiber::suspend()', $result);
        self::assertStringNotContainsString('$fiberCurrent::suspend()', $result);

        require_once $path;
        $greenThreadClass = $this->runtimeFixtureClass('GreenThread');
        $fiber = new \Fiber(static function () use ($greenThreadClass): void {
            $greenThread = (new \ReflectionClass($greenThreadClass))->newInstance();
            (new \ReflectionMethod($greenThread, 'suspendCurrent'))->invoke($greenThread);
        });
        $fiber->start();
        self::assertTrue($fiber->isSuspended());
        $fiber->resume();
        self::assertTrue($fiber->isTerminated());
    }

    public function test_dynamic_static_call_without_exact_current_fiber_guard_is_rejected(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'Unsafe.php', <<<'PHP'
<?php
namespace Fixture;

use Fiber;

final class Unsafe
{
    public function suspend(): void
    {
        $fiberCurrent = Fiber::getCurrent();
        if ($fiberCurrent != null) {
            $fiberCurrent::suspend();
        }
    }
}
PHP);

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('dynamic class reference');

        (new NamespaceShader())->shadeDirectory($directory, ['Shared\\Virion' => 'Fixture\\Shade\\Shared']);
    }

    public function test_current_fiber_dynamic_method_other_than_suspend_is_rejected(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'Unsafe.php', <<<'PHP'
<?php
namespace Fixture;

use Fiber;

final class Unsafe
{
    public function resumeCurrent(): void
    {
        $fiberCurrent = Fiber::getCurrent();
        if ($fiberCurrent !== null) {
            $fiberCurrent::resume();
        }
    }
}
PHP);

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('dynamic class reference');

        (new NamespaceShader())->shadeDirectory($directory, ['Shared\\Virion' => 'Fixture\\Shade\\Shared']);
    }

    public function test_reflection_of_late_bound_called_class_uses_the_bound_object(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        $path = $directory . DIRECTORY_SEPARATOR . 'Thread.php';
        file_put_contents($path, <<<'PHP'
<?php
namespace Fixture;

use Fiber;
use ReflectionClass;
use function get_called_class;

class Thread
{
    public function reflectedClass(): string
    {
        $reflectedClass = '';
        $fiber = new Fiber(function () use (&$reflectedClass): void {
            $className = get_called_class();
            $reflection = new ReflectionClass($className);
            $reflectedClass = $reflection->getName();
        });
        $fiber->start();

        return $reflectedClass;
    }
}

final class ConcreteThread extends Thread
{
}
PHP);

        $shader = new NamespaceShader();
        $shader->shadeDirectory($directory, ['Shared\\Virion' => 'Fixture\\Shade\\Shared']);
        $shader->assertNoNamespaceReferences($directory, ['Shared\\Virion']);

        $result = file_get_contents($path);
        self::assertIsString($result);
        self::assertStringContainsString('new ReflectionClass($this)', $result);
        self::assertStringNotContainsString('new ReflectionClass($className)', $result);

        require_once $path;
        $threadClass = $this->runtimeFixtureClass('ConcreteThread');
        $thread = (new \ReflectionClass($threadClass))->newInstance();
        self::assertSame('Fixture\\ConcreteThread', (new \ReflectionMethod($thread, 'reflectedClass'))->invoke($thread));
    }

    public function test_arbitrary_reflection_class_string_is_still_rejected(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'UnsafeReflection.php', <<<'PHP'
<?php
namespace Fixture;

use ReflectionClass;

final class UnsafeReflection
{
    public function reflect(string $className): ReflectionClass
    {
        $reflection = new ReflectionClass($className);
        return $reflection;
    }
}
PHP);

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('dynamic ReflectionClass');

        (new NamespaceShader())->shadeDirectory($directory, ['Shared\\Virion' => 'Fixture\\Shade\\Shared']);
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

    public function test_annotated_guarded_environment_driver_is_allowed(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        $path = $directory . DIRECTORY_SEPARATOR . 'Factory.php';
        file_put_contents(
            $path,
            <<<'PHP'
<?php
namespace Fixture;

use Shared\Virion\Driver;
use function class_exists;
use function getenv;
use function is_subclass_of;

final class Factory
{
    /** @devtools-allow-dynamic-class $driver */
    public function create(): object
    {
        $driver = getenv('DRIVER');
        if (!class_exists($driver)) {
            throw new \Error('missing');
        }
        if (!is_subclass_of($driver, Driver::class)) {
            throw new \Error('invalid');
        }

        return new $driver();
    }
}
PHP,
        );

        $shader = new NamespaceShader();
        $shader->shadeDirectory($directory, ['Shared\Virion' => 'Fixture\Shade\Shared']);
        $shader->assertNoNamespaceReferences($directory, ['Shared\Virion']);

        $result = file_get_contents($path);
        self::assertIsString($result);
        self::assertStringContainsString('Fixture\Shade\Shared\Driver::class', $result);
    }

    public function test_annotated_dynamic_class_without_guards_is_rejected(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        file_put_contents(
            $directory . DIRECTORY_SEPARATOR . 'Unsafe.php',
            <<<'PHP'
<?php
final class Unsafe
{
    /** @devtools-allow-dynamic-class $className */
    public function create(): object
    {
        $className = getenv('CLASS_NAME');

        return new $className();
    }

}
PHP,
        );

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('dynamic class reference');

        (new NamespaceShader())->shadeDirectory($directory, ['Shared\Virion' => 'Fixture\Shade\Shared']);
    }

    public function test_annotated_dynamic_class_with_unreachable_guards_is_rejected(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        file_put_contents(
            $directory . DIRECTORY_SEPARATOR . 'Unsafe.php',
            <<<'PHP'
<?php
namespace Fixture;
use Shared\Virion\Driver;
final class Unsafe
{
    /** @devtools-allow-dynamic-class $driver */
    public function create(string $driver): object
    {
        if (false) {
            class_exists($driver);
            is_subclass_of($driver, Driver::class);
        }
        return new $driver();
    }
}
PHP,
        );

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('dynamic class reference');

        (new NamespaceShader())->shadeDirectory($directory, ['Shared\Virion' => 'Fixture\Shade\Shared']);
    }

    public function test_annotated_dynamic_class_with_reassigned_name_is_rejected(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        file_put_contents(
            $directory . DIRECTORY_SEPARATOR . 'Unsafe.php',
            <<<'PHP'
<?php
namespace Fixture;
use Shared\Virion\Driver;
final class Unsafe
{
    /** @devtools-allow-dynamic-class $driver */
    public function create(string $driver): object
    {
        if (!class_exists($driver)) {
            throw new \Error('missing');
        }
        if (!is_subclass_of($driver, Driver::class)) {
            throw new \Error('invalid');
        }
        $driver = getenv('UNVALIDATED_DRIVER');
        return new $driver();
    }
}
PHP,
        );

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('dynamic class reference');

        (new NamespaceShader())->shadeDirectory($directory, ['Shared\Virion' => 'Fixture\Shade\Shared']);
    }

    public function test_annotated_dynamic_class_match_is_allowed(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        $path = $directory . DIRECTORY_SEPARATOR . 'Matcher.php';
        file_put_contents(
            $path,
            <<<'PHP'
<?php
namespace Fixture;

use Shared\Virion\Callback;

final class Matcher
{
    /** @devtools-allow-dynamic-class $callback */
    public function type(object $callback): int
    {
        return match ($callback::class) {
            Callback::class => 1,
            default => 0,
        };
    }
}
PHP,
        );

        $shader = new NamespaceShader();
        $shader->shadeDirectory($directory, ['Shared\Virion' => 'Fixture\Shade\Shared']);
        $shader->assertNoNamespaceReferences($directory, ['Shared\Virion']);

        $result = file_get_contents($path);
        self::assertIsString($result);
        self::assertStringContainsString('Fixture\Shade\Shared\Callback::class', $result);
    }

    public function test_guarded_object_class_without_annotation_is_allowed(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        $path = $directory . DIRECTORY_SEPARATOR . 'Translator.php';
        file_put_contents(
            $path,
            <<<'PHP'
<?php
namespace Fixture;

use Shared\Virion\Graphic;

final class Translator
{
    public function describe(mixed $graphic): string
    {
        $graphic instanceof Graphic || throw new \InvalidArgumentException();

        return $graphic::class;
    }
}
PHP,
        );

        $shader = new NamespaceShader();
        $shader->shadeDirectory($directory, ['Shared\Virion' => 'Fixture\Shade\Shared']);
        $shader->assertNoNamespaceReferences($directory, ['Shared\Virion']);

        $result = file_get_contents($path);
        self::assertIsString($result);
        self::assertStringContainsString('Fixture\Shade\Shared\Graphic', $result);
        self::assertStringContainsString('$graphic::class', $result);
    }

    public function test_object_class_in_short_circuit_guard_diagnostic_is_allowed(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        $path = $directory . DIRECTORY_SEPARATOR . 'Translator.php';
        file_put_contents(
            $path,
            <<<'PHP'
<?php
namespace Fixture;

use Shared\Virion\Graphic;

final class Translator
{
    public function describe(mixed $graphic): void
    {
        $graphic instanceof Graphic || throw new \InvalidArgumentException(
            "Expected " . Graphic::class . ", got " . $graphic::class,
        );
    }
}
PHP,
        );

        $shader = new NamespaceShader();
        $shader->shadeDirectory($directory, ['Shared\\Virion' => 'Fixture\\Shade\\Shared']);
        $shader->assertNoNamespaceReferences($directory, ['Shared\\Virion']);

        $result = file_get_contents($path);
        self::assertIsString($result);
        self::assertStringContainsString('Fixture\\Shade\\Shared\\Graphic::class', $result);
        self::assertStringContainsString('$graphic::class', $result);
    }

    public function test_object_class_in_short_circuit_guard_for_another_variable_is_rejected(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        file_put_contents(
            $directory . DIRECTORY_SEPARATOR . 'Translator.php',
            <<<'PHP'
<?php
namespace Fixture;

use Shared\Virion\Graphic;

final class Translator
{
    public function describe(mixed $graphic, mixed $other): void
    {
        $graphic instanceof Graphic || throw new \InvalidArgumentException($other::class);
    }
}
PHP,
        );

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('dynamic class reference');
        (new NamespaceShader())->shadeDirectory($directory, ['Shared\\Virion' => 'Fixture\\Shade\\Shared']);
    }

    public function test_conditional_object_guard_is_rejected(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'Conditional.php', <<<'PHP'
<?php
namespace Fixture;
use Shared\Virion\Graphic;
final class Conditional
{
    public function describe(bool $check, mixed $graphic): string
    {
        if ($check) {
            $graphic instanceof Graphic || throw new \InvalidArgumentException();
        }
        return $graphic::class;
    }
}
PHP);

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('dynamic class reference');
        (new NamespaceShader())->shadeDirectory($directory, ['Shared\Virion' => 'Fixture\Shade\Shared']);
    }

    public function test_object_guard_invalidated_by_reassignment_is_rejected(): void
    {
        $directory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src';
        $this->filesystem->ensureDirectory($directory);
        file_put_contents($directory . DIRECTORY_SEPARATOR . 'Reassigned.php', <<<'PHP'
<?php
namespace Fixture;
use Shared\Virion\Graphic;
final class Reassigned
{
    public function describe(mixed $graphic): string
    {
        $graphic instanceof Graphic || throw new \InvalidArgumentException();
        $graphic = Graphic::class;
        return $graphic::class;
    }
}
PHP);

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('dynamic class reference');
        (new NamespaceShader())->shadeDirectory($directory, ['Shared\Virion' => 'Fixture\Shade\Shared']);
    }

    /** @return class-string<object> */
    private function runtimeFixtureClass(string $shortName): string
    {
        $className = 'Fixture\\' . $shortName;
        if (!class_exists($className)) {
            self::fail('The runtime fixture class was not loaded.');
        }

        return $className;
    }
}
