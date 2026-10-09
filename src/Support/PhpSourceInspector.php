<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Support;

use function file_get_contents;
use function in_array;
use function is_array;
use function ltrim;

use ParseError;

use function token_get_all;
use function trim;

final class PhpSourceInspector
{
    public function inspect(string $path): PhpFileSymbols
    {
        $source = file_get_contents($path);
        if ($source === false) {
            throw new FilesystemException("Cannot read PHP source: {$path}");
        }

        try {
            $tokens = token_get_all($source, TOKEN_PARSE);
        } catch (ParseError $error) {
            throw new FilesystemException("PHP syntax error in {$path}: {$error->getMessage()}", 0, $error);
        }

        $namespace = '';
        $namespaces = [];
        $classes = [];
        $previousSignificant = null;
        $count = count($tokens);
        for ($index = 0; $index < $count; ++$index) {
            $token = $tokens[$index];
            if (!is_array($token)) {
                if (trim($token) !== '') {
                    $previousSignificant = $token;
                }
                continue;
            }

            [$id] = $token;
            if ($id === T_NAMESPACE) {
                $namespace = $this->readName($tokens, $index);
                if (!in_array($namespace, $namespaces, true)) {
                    $namespaces[] = $namespace;
                }
            } elseif (
                in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)
                && !in_array($previousSignificant, [T_NEW, T_DOUBLE_COLON], true)
            ) {
                $name = $this->readDeclarationName($tokens, $index);
                if ($name !== null) {
                    $classes[] = ltrim($namespace . '\\' . $name, '\\');
                }
            }

            if (!in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $previousSignificant = $id;
            }
        }

        return new PhpFileSymbols($classes, $namespaces);
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private function readName(array $tokens, int &$index): string
    {
        $name = '';
        $count = count($tokens);
        for (++$index; $index < $count; ++$index) {
            $token = $tokens[$index];
            if (is_string($token)) {
                if ($token === ';' || $token === '{') {
                    break;
                }
                continue;
            }
            if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                $name .= $token[1];
            }
        }

        return ltrim($name, '\\');
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private function readDeclarationName(array $tokens, int $index): ?string
    {
        $count = count($tokens);
        for (++$index; $index < $count; ++$index) {
            $token = $tokens[$index];
            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }
            if (is_string($token) && ($token === '{' || $token === '(' || $token === ';')) {
                return null;
            }
        }

        return null;
    }
}
