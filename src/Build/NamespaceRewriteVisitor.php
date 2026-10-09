<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Build;

use function ltrim;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeVisitorAbstract;

use function str_starts_with;
use function strlen;
use function strpos;
use function strtolower;

final class NamespaceRewriteVisitor extends NodeVisitorAbstract
{
    /**
     * @param array<string, string> $replacements Original namespace => shaded namespace.
     */
    public function __construct(private readonly array $replacements) {}

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof String_ || $node instanceof InterpolatedStringPart) {
            foreach ($this->replacements as $original => $replacement) {
                if ($this->containsNamespace($node->value, $original)) {
                    throw new BuildException(
                        "Cannot safely shade namespace {$original}: it appears in a PHP string literal on line {$node->getStartLine()}. "
                        . 'Replace dynamic class-name strings with static ::class references or exclude this virion.',
                    );
                }
            }

            return null;
        }

        if ($node instanceof Concat) {
            $value = $this->staticString($node);
            if ($value !== null) {
                foreach ($this->replacements as $original => $replacement) {
                    if ($this->containsNamespace($value, $original)) {
                        throw new BuildException(
                            "Cannot safely shade namespace {$original}: it is assembled by string concatenation on line {$node->getStartLine()}.",
                        );
                    }
                }
            }
        }

        if (($node instanceof New_ || $node instanceof Instanceof_ || $node instanceof StaticCall
            || $node instanceof StaticPropertyFetch || $node instanceof ClassConstFetch)
            && $node->class instanceof Expr) {
            throw new BuildException(
                "Cannot safely shade a dynamic class reference on line {$node->getStartLine()}. Use a static class name or ::class reference.",
            );
        }

        if ($node instanceof FuncCall && $node->name instanceof Name) {
            $function = strtolower(ltrim($node->name->toString(), '\\'));
            $arguments = match ($function) {
                'class_alias' => [0, 1],
                'class_exists', 'interface_exists', 'trait_exists', 'enum_exists' => [0],
                'is_a', 'is_subclass_of' => [1],
                default => [],
            };
            foreach ($arguments as $argument) {
                if (isset($node->args[$argument]) && $node->args[$argument] instanceof Arg
                    && !$this->isStaticClassExpression($node->args[$argument]->value)) {
                    throw new BuildException(
                        "Cannot safely shade dynamic class-name argument to {$function}() on line {$node->getStartLine()}.",
                    );
                }
            }
        }

        if ($node instanceof New_ && $node->class instanceof Name && isset($node->args[0]) && $node->args[0] instanceof Arg) {
            $class = strtolower(ltrim($node->class->toString(), '\\'));
            if (in_array($class, ['reflectionclass', 'reflectionenum'], true)
                && !$this->isStaticClassExpression($node->args[0]->value)) {
                throw new BuildException(
                    "Cannot safely shade dynamic {$node->class->toString()} construction on line {$node->getStartLine()}.",
                );
            }
        }

        if ($node instanceof GroupUse) {
            $prefix = $node->prefix->toString();
            foreach ($node->uses as $use) {
                $combined = $prefix . '\\' . $use->name->toString();
                foreach ($this->replacements as $original => $replacement) {
                    if ($this->matchesNamespace($combined, $original)) {
                        throw new BuildException(
                            "Cannot safely shade grouped import {$combined} on line {$node->getStartLine()}. "
                            . 'Replace the grouped import with ordinary use statements.',
                        );
                    }
                }
            }
        }

        if (!$node instanceof Name) {
            return null;
        }

        $parent = $node->getAttribute('parent');
        $isDeclarationOrImport = ($parent instanceof Namespace_ && $parent->name === $node)
            || ($parent instanceof UseUse && $parent->name === $node)
            || ($parent instanceof GroupUse && $parent->prefix === $node);
        $resolved = $node->getAttribute('resolvedName');
        $candidate = $resolved instanceof Name ? $resolved->toString() : $node->toString();
        $replacement = $this->replacementFor($candidate);
        if ($replacement === null) {
            return null;
        }

        if ($isDeclarationOrImport) {
            return new Name($replacement, $node->getAttributes());
        }

        if ($node instanceof FullyQualified || $resolved instanceof Name) {
            return new FullyQualified($replacement, $node->getAttributes());
        }

        return null;
    }

    private function replacementFor(string $candidate): ?string
    {
        foreach ($this->replacements as $original => $replacement) {
            if ($this->matchesNamespace($candidate, $original)) {
                return $replacement . substr($candidate, strlen($original));
            }
        }

        return null;
    }

    private function matchesNamespace(string $candidate, string $namespace): bool
    {
        $candidate = strtolower(ltrim($candidate, '\\'));
        $namespace = strtolower(ltrim($namespace, '\\'));

        return $candidate === $namespace || str_starts_with($candidate, $namespace . '\\');
    }

    private function containsNamespace(string $value, string $namespace): bool
    {
        $value = strtolower(str_replace('\\\\', '\\', $value));
        $namespace = strtolower(ltrim($namespace, '\\'));

        $offset = 0;
        while (($position = strpos($value, $namespace, $offset)) !== false) {
            $end = $position + strlen($namespace);
            if ($end === strlen($value) || $value[$end] === '\\') {
                return true;
            }
            $offset = $position + 1;
        }

        return false;
    }

    private function isStaticClassExpression(Expr $expression): bool
    {
        return $this->staticString($expression) !== null
            || ($expression instanceof ClassConstFetch && $expression->class instanceof Name);
    }

    private function staticString(Expr $expression): ?string
    {
        if ($expression instanceof String_) {
            return $expression->value;
        }
        if (!$expression instanceof Concat) {
            return null;
        }
        $left = $this->staticString($expression->left);
        $right = $this->staticString($expression->right);

        return $left === null || $right === null ? null : $left . $right;
    }
}
