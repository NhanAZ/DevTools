<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Build;

use function array_search;
use function is_string;
use function ltrim;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
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
    /**
     * Allows the target type argument to is_a() when PHP's allow_string argument is omitted.
     * With its default false value, the dynamic target type is only compared; it is not loaded.
     */
    public static function allowsNonAutoloadingIsATypeArgument(FuncCall $node): bool
    {
        $name = $node->name;
        if (!$name instanceof Name || strtolower(ltrim($name->toString(), '\\')) !== 'is_a'
            || count($node->args) !== 2
            || !$node->args[1] instanceof Arg
            || !$node->args[1]->value instanceof Variable) {
            return false;
        }

        $resolvedName = $name->getAttribute('resolvedName');
        return $name->isFullyQualified()
            || ($resolvedName instanceof Name && $resolvedName->isFullyQualified()
                && strtolower(ltrim($resolvedName->toString(), '\\')) === 'is_a');
    }

    /** The variable class operand of instanceof performs a type check without autoloading. */
    public static function allowsNonAutoloadingInstanceofTarget(Instanceof_ $node): bool
    {
        return $node->class instanceof Variable && is_string($node->class->name);
    }

    /**
     * Allows rewriting the exact Fiber::getCurrent(), non-null guard, suspend pattern.
     * The native Fiber is final, getCurrent() returns Fiber|null, and suspend() targets
     * the currently executing Fiber, so this dynamic object-class call is equivalent to
     * a direct \Fiber::suspend() call. Other dynamic static calls remain rejected.
     */
    public static function allowsCurrentFiberSuspend(StaticCall $node): bool
    {
        if (!$node->class instanceof Variable || !is_string($node->class->name)
            || !$node->name instanceof Identifier || strtolower($node->name->toString()) !== 'suspend'
            || $node->args !== []) {
            return false;
        }

        $variable = $node->class->name;
        $statement = $node->getAttribute('parent');
        if (!$statement instanceof ExpressionStatement || $statement->expr !== $node) {
            return false;
        }

        $guard = $statement->getAttribute('parent');
        if (!$guard instanceof If_ || $guard->else !== null || $guard->elseifs !== []
            || count($guard->stmts) !== 1 || $guard->stmts[0] !== $statement
            || !$guard->cond instanceof NotIdentical
            || !$guard->cond->left instanceof Variable || $guard->cond->left->name !== $variable
            || !$guard->cond->right instanceof ConstFetch
            || strtolower($guard->cond->right->name->toString()) !== 'null') {
            return false;
        }

        $function = self::enclosingFunction($node);
        if ($function === null) {
            return false;
        }

        $statements = $function->getStmts() ?? [];
        $index = array_search($guard, $statements, true);
        if (!is_int($index) || $index === 0
            || !$statements[$index - 1] instanceof ExpressionStatement
            || !$statements[$index - 1]->expr instanceof Assign
            || !$statements[$index - 1]->expr->var instanceof Variable
            || $statements[$index - 1]->expr->var->name !== $variable
            || !$statements[$index - 1]->expr->expr instanceof StaticCall) {
            return false;
        }

        $currentFiber = $statements[$index - 1]->expr->expr;
        if (!$currentFiber->class instanceof Name || !$currentFiber->name instanceof Identifier
            || strtolower($currentFiber->name->toString()) !== 'getcurrent' || $currentFiber->args !== []) {
            return false;
        }

        return self::isGlobalName($currentFiber->class, 'fiber');
    }

    /**
     * Allows replacing ReflectionClass($className) only when the same non-static closure
     * has just assigned get_called_class() to that variable. In this exact instance-method
     * context, reflecting $this preserves the late-bound class without a dynamic class string.
     */
    public static function allowsCalledClassReflection(New_ $node): bool
    {
        if (!$node->class instanceof Name || !self::isGlobalName($node->class, 'reflectionclass')
            || count($node->args) !== 1 || !$node->args[0] instanceof Arg || $node->args[0]->unpack
            || $node->args[0]->name !== null || !$node->args[0]->value instanceof Variable
            || !is_string($node->args[0]->value->name)) {
            return false;
        }

        $reflectionAssignment = $node->getAttribute('parent');
        if (!$reflectionAssignment instanceof Assign || $reflectionAssignment->expr !== $node) {
            return false;
        }

        $statement = $reflectionAssignment->getAttribute('parent');
        $function = self::enclosingFunction($node);
        if (!$statement instanceof ExpressionStatement || $statement->expr !== $reflectionAssignment
            || !$function instanceof Closure || $function->static || !self::hasBoundThis($node)) {
            return false;
        }

        $statements = $function->getStmts();
        $index = array_search($statement, $statements, true);
        if (!is_int($index) || $index === 0
            || !$statements[$index - 1] instanceof ExpressionStatement
            || !$statements[$index - 1]->expr instanceof Assign
            || !$statements[$index - 1]->expr->var instanceof Variable
            || $statements[$index - 1]->expr->var->name !== $node->args[0]->value->name
            || !$statements[$index - 1]->expr->expr instanceof FuncCall) {
            return false;
        }

        $calledClass = $statements[$index - 1]->expr->expr;
        return $calledClass->name instanceof Name && $calledClass->args === []
            && self::isGlobalName($calledClass->name, 'get_called_class');
    }

    /** ReflectionClass accepts an already-instantiated object without class-name autoloading. */
    public static function allowsBoundObjectReflection(New_ $node): bool
    {
        return $node->class instanceof Name && self::isGlobalName($node->class, 'reflectionclass')
            && count($node->args) === 1 && $node->args[0] instanceof Arg && !$node->args[0]->unpack
            && $node->args[0]->name === null && $node->args[0]->value instanceof Variable
            && $node->args[0]->value->name === 'this' && self::hasBoundThis($node);
    }

    private static function hasBoundThis(Node $node): bool
    {
        $parent = $node->getAttribute('parent');
        while ($parent instanceof Node) {
            if ($parent instanceof Closure || $parent instanceof ArrowFunction) {
                if ($parent->static) {
                    return false;
                }
            } elseif ($parent instanceof ClassMethod) {
                return !$parent->isStatic();
            } elseif ($parent instanceof FunctionLike) {
                return false;
            }
            $parent = $parent->getAttribute('parent');
        }

        return false;
    }

    private static function isGlobalName(Name $name, string $expected): bool
    {
        $resolvedName = $name->getAttribute('resolvedName');
        return $resolvedName instanceof Name && $resolvedName->isFullyQualified()
            && strtolower(ltrim($resolvedName->toString(), '\\')) === $expected;
    }

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
