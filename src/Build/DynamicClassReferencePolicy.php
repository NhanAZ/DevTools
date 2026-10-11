<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Build;

use function array_search;
use function is_string;
use function ltrim;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression as ExpressionStatement;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;

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

        $parent = $node->getAttribute('parent');
        if (!$parent instanceof Return_ || $parent->expr !== $node) {
            return false;
        }

        $statements = $function->getStmts() ?? [];
        $index = array_search($parent, $statements, true);
        return is_int($index)
            && $index >= 2
            && self::isThrowingGuard($statements[$index - 2], 'class_exists', $variable)
            && self::isThrowingGuard($statements[$index - 1], 'is_subclass_of', $variable);
    }

    private static function isThrowingGuard(Node $statement, string $function, string $variable): bool
    {
        if (!$statement instanceof If_ || $statement->else !== null || $statement->elseifs !== []
            || count($statement->stmts) !== 1
            || !$statement->stmts[0] instanceof ExpressionStatement
            || !$statement->stmts[0]->expr instanceof Throw_
            || !$statement->cond instanceof BooleanNot
            || !$statement->cond->expr instanceof FuncCall) {
            return false;
        }

        $call = $statement->cond->expr;
        $name = $call->name;
        if (!$name instanceof Name
            || strtolower(ltrim($name->toString(), '\\')) !== $function
            || !isset($call->args[0]) || !$call->args[0] instanceof Arg
            || !$call->args[0]->value instanceof Variable
            || $call->args[0]->value->name !== $variable) {
            return false;
        }

        if ($function === 'is_subclass_of') {
            return isset($call->args[1]) && $call->args[1] instanceof Arg
                && $call->args[1]->value instanceof ClassConstFetch
                && $call->args[1]->value->class instanceof Name;
        }
        return true;
    }

    public static function allowsObjectClass(Node $node): bool
    {
        if (!$node instanceof ClassConstFetch
            || !$node->name instanceof Identifier
            || $node->name->toString() !== 'class'
            || !$node->class instanceof Variable) {
            return false;
        }

        return self::allowsAnnotated($node, $node->class)
            || self::hasGuardedObjectClass($node, $node->class);
    }

    private static function hasGuardedObjectClass(Node $node, Variable $class): bool
    {
        $function = self::enclosingFunction($node);
        $parent = $node->getAttribute('parent');
        if ($function === null || !$parent instanceof Return_ || $parent->expr !== $node) {
            return false;
        }

        $statements = $function->getStmts() ?? [];
        $index = array_search($parent, $statements, true);
        if (!is_int($index) || $index === 0) {
            return false;
        }

        $previous = $statements[$index - 1];
        if (!$previous instanceof ExpressionStatement || !$previous->expr instanceof BooleanOr) {
            return false;
        }

        $guard = $previous->expr;
        return $guard->left instanceof Instanceof_
            && $guard->right instanceof Throw_
            && $guard->left->expr instanceof Variable
            && $guard->left->expr->name === $class->name
            && $guard->left->class instanceof Name;
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
