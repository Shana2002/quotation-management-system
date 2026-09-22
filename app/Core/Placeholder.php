<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Placeholder
 *
 * Tiny `${token}` substitution engine for admin-authored letter text. A plan's
 * Investment Summary and Terms & Conditions templates are written once (on the
 * plan, in plan edit mode) with tokens such as `${amount}` or
 * `${monthly_return}`, and resolved per quotation from the flat token map that
 * the plan type's compute() returns.
 *
 * Resolution happens at quotation-creation time and the resolved lines are
 * snapshotted into the projection — so editing a plan later never rewrites a
 * historical quotation.
 */
final class Placeholder
{
    /** Matches `${token_name}` (inner whitespace tolerated). */
    private const PATTERN = '/\$\{\s*([A-Za-z0-9_]+)\s*\}/';

    /**
     * Replace every recognised `${token}` with its value. Unknown tokens are
     * left untouched so a typo stays visible rather than silently blanking out.
     *
     * @param array<string,mixed> $tokens
     */
    public static function resolve(string $template, array $tokens): string
    {
        return (string) preg_replace_callback(
            self::PATTERN,
            static function (array $m) use ($tokens): string {
                return array_key_exists($m[1], $tokens) ? (string) $tokens[$m[1]] : $m[0];
            },
            $template
        );
    }

    /**
     * Resolve a multi-line template into its non-empty lines, stripping any
     * leading list marker (bullet, dash, "1.", "1)") — renderers re-add their
     * own marker so bullets/numbering stay sequential after rows are dropped.
     *
     * @param array<string,mixed> $tokens
     * @return string[]
     */
    public static function lines(string $template, array $tokens): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $template) ?: [];
        $out   = [];

        foreach ($lines as $line) {
            $clean = trim((string) preg_replace(
                '/^\s*(?:[\x{2022}\x{00B7}\x{25CF}\-\*]|\d+[.)])\s*/u',
                '',
                trim($line)
            ));

            if ($clean === '') {
                continue;
            }

            $out[] = self::resolve($clean, $tokens);
        }

        return $out;
    }

    /**
     * Token names a template references, in order of first appearance.
     *
     * @return string[]
     */
    public static function referenced(string $template): array
    {
        preg_match_all(self::PATTERN, $template, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Referenced token names the plan type does not supply — used to warn an
     * admin about a misspelled token while they can still fix it.
     *
     * @param array<string,mixed> $available Token map (keys are the names).
     * @return string[]
     */
    public static function unknown(string $template, array $available): array
    {
        return array_values(array_diff(self::referenced($template), array_keys($available)));
    }
}
