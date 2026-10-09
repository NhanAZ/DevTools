<?php

declare(strict_types=1);

namespace NhanAZ\DevTools\Virion;

use function explode;
use function preg_match;
use function preg_split;
use function sprintf;
use function trim;
use function version_compare;

final class VirionVersionConstraint
{
    private const VERSION_PATTERN = '(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?';

    /** @var list<string> */
    private readonly array $tokens;

    public readonly string $expression;

    public function __construct(string $expression)
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw new VirionException('A virion version constraint may not be empty. Use * for any version.');
        }

        $tokens = preg_split('/\s+/', $expression) ?: [];
        foreach ($tokens as $token) {
            if (!$this->isValidToken($token)) {
                throw new VirionException(
                    "Unsupported virion version constraint \"{$expression}\". "
                    . 'Use *, an exact version, ^1.2.3, ~1.2.3, 1.2.*, or comparisons such as >=1.2.0 <2.0.0.',
                );
            }
        }
        $this->expression = $expression;
        $this->tokens = $tokens;
    }

    public static function isValidVersion(string $version): bool
    {
        return preg_match('/^' . self::VERSION_PATTERN . '$/D', $version) === 1;
    }

    public function matches(string $version): bool
    {
        if (!self::isValidVersion($version)) {
            return false;
        }
        foreach ($this->tokens as $token) {
            if (!$this->tokenMatches($version, $token)) {
                return false;
            }
        }

        return true;
    }

    private function isValidToken(string $token): bool
    {
        return $token === '*'
            || preg_match('/^(?:\^|~|<=|>=|<|>|=)?' . self::VERSION_PATTERN . '$/D', $token) === 1
            || preg_match('/^(?:0|[1-9][0-9]*)\.\*$/D', $token) === 1
            || preg_match('/^(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.\*$/D', $token) === 1;
    }

    private function tokenMatches(string $version, string $token): bool
    {
        if ($token === '*') {
            return true;
        }
        if (preg_match('/^(?<major>0|[1-9][0-9]*)\.\*$/D', $token, $match) === 1) {
            $minimum = $match['major'] . '.0.0';
            $maximum = sprintf('%d.0.0', (int) $match['major'] + 1);

            return $this->between($version, $minimum, $maximum);
        }
        if (preg_match('/^(?<major>0|[1-9][0-9]*)\.(?<minor>0|[1-9][0-9]*)\.\*$/D', $token, $match) === 1) {
            $minimum = $match['major'] . '.' . $match['minor'] . '.0';
            $maximum = sprintf('%d.%d.0', (int) $match['major'], (int) $match['minor'] + 1);

            return $this->between($version, $minimum, $maximum);
        }
        if ($token[0] === '^' || $token[0] === '~') {
            $minimum = substr($token, 1);
            [$major, $minor, $patch] = $this->numericParts($minimum);
            if ($token[0] === '~') {
                $maximum = sprintf('%d.%d.0', $major, $minor + 1);
            } elseif ($major > 0) {
                $maximum = sprintf('%d.0.0', $major + 1);
            } elseif ($minor > 0) {
                $maximum = sprintf('0.%d.0', $minor + 1);
            } else {
                $maximum = sprintf('0.0.%d', $patch + 1);
            }

            return $this->between($version, $minimum, $maximum);
        }
        if (preg_match('/^(?<operator><=|>=|<|>|=)(?<version>.+)$/D', $token, $match) === 1) {
            return version_compare($this->comparable($version), $this->comparable($match['version']), $match['operator']);
        }

        return version_compare($this->comparable($version), $this->comparable($token), '=');
    }

    private function between(string $version, string $minimum, string $maximum): bool
    {
        $version = $this->comparable($version);

        return version_compare($version, $this->comparable($minimum), '>=')
            && version_compare($version, $this->comparable($maximum), '<');
    }

    /** @return array{int, int, int} */
    private function numericParts(string $version): array
    {
        $core = explode('-', explode('+', $version, 2)[0], 2)[0];
        [$major, $minor, $patch] = explode('.', $core);

        return [(int) $major, (int) $minor, (int) $patch];
    }

    private function comparable(string $version): string
    {
        return explode('+', $version, 2)[0];
    }
}
