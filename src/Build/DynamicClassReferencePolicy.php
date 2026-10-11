<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Build;

use function is_string;
use function ltrim;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

use function str_contains;
use function strtolower;

/**
 * Allows only explicitly annotated and validated runtime class factories.
 *
 * The annotation is intentionally attached to the function that owns the
 * dynamic class reference. It is not a general bypass for namespace safety.
 */
final class DynamicClassReferencePolicy
{
    public static function allowsAnnotated(Node $node, Expr $class): bool
    {
        $variable = self::variableName($class);
        $function = self::enclosingFunction($node);
        if ($variable === null || $function === null) {
            return false;
        }

        $comment = $function->getDocComment();
        if ($comment === null) {
            return false;
        }

        return str_contains($comment->getText(), '@devtools-allow-dynamic-class $' . $variable);
    }

    public static function allowsGuarded(Node $node, Expr $class): bool
    {
        if (!self::allowsAnnotated($node, $class)) {
            return false;
        }

        $variable = self::variableName($class);
        $function = self::enclosingFunction($node);
        if ($variable === null || $function === null) {
            return false;
        }

        $visitor = new class ($node, $variable) extends NodeVisitorAbstract {
            private bool $classExistsChecked = false;

            private bool $subclassChecked = false;

            public function __construct(
                private readonly Node $target,
                private readonly string $variable,
            ) {}

            public function isGuarded(): bool
            {
                return $this->classExistsChecked && $this->subclassChecked;
            }

            public function enterNode(Node $node): ?int
            {
                if ($node === $this->target) {
                    return self::STOP_TRAVERSAL;
                }

                if (!$node instanceof FuncCall || !$node->name instanceof Name) {
                    return null;
                }

                $function = strtolower(ltrim($node->name->toString(), '\\'));
                if ($function === 'class_exists' && $this->hasVariableArgument($node, 0)) {
                    $this->classExistsChecked = true;
                }
                if ($function === 'is_subclass_of'
                    && $this->hasVariableArgument($node, 0)
                    && isset($node->args[1])
                    && $node->args[1] instanceof Arg
                    && $this->isStaticClassExpression($node->args[1]->value)) {
                    $this->subclassChecked = true;
                }

                return null;
            }

            private function hasVariableArgument(FuncCall $call, int $index): bool
            {
                return isset($call->args[$index])
                    && $call->args[$index] instanceof Arg
                    && $call->args[$index]->value instanceof Variable
                    && $call->args[$index]->value->name === $this->variable;
            }

            private function isStaticClassExpression(Expr $expression): bool
            {
                return $expression instanceof ClassConstFetch && $expression->class instanceof Name;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($function->getStmts() ?? []);

        return $visitor->isGuarded();
    }

    public static function allowsObjectClass(Node $node): bool
    {
        if (!$node instanceof ClassConstFetch
            || !$node->name instanceof Identifier
            || $node->name->toString() !== 'class'
            || !$node->class instanceof Variable) {
            return false;
        }

        return self::allowsAnnotated($node, $node->class);
    }

    private static function variableName(Expr $class): ?string
    {
        return $class instanceof Variable && is_string($class->name) ? $class->name : null;
    }

    private static function enclosingFunction(Node $node): ?FunctionLike
    {
        $parent = $node->getAttribute('parent');
        while ($parent instanceof Node) {
            if ($parent instanceof FunctionLike) {
                return $parent;
            }
            $parent = $parent->getAttribute('parent');
        }

        return null;
    }
}
