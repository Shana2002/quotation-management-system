<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Csrf
 *
 * Synchroniser-token-pattern CSRF protection. A per-session token is
 * embedded in every state-changing form and validated on POST/PUT/DELETE.
 */
final class Csrf
{
    private const KEY = '__csrf_token';

    /**
     * Return the current token, generating one if necessary.
     */
    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY];
    }

    /**
     * Render a hidden input field carrying the token.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    /**
     * Constant-time validation of a submitted token.
     */
    public static function validate(?string $token): bool
    {
        if (empty($_SESSION[self::KEY]) || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($_SESSION[self::KEY], $token);
    }
}
