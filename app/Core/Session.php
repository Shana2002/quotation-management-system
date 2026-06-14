<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session
 *
 * Centralised, hardened session management:
 *  - HttpOnly + SameSite cookies, Secure flag over HTTPS
 *  - id regeneration on login (anti session-fixation)
 *  - absolute lifetime + idle timeout enforcement
 */
final class Session
{
    private static bool $started = false;

    /**
     * Start the session with secure cookie parameters.
     *
     * @param array<string,mixed> $config The 'session' config array.
     */
    public static function start(array $config): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443);

        session_name($config['name'] ?? 'QMS_SESSION');
        session_set_cookie_params([
            'lifetime' => 0,            // until browser closes; absolute lifetime enforced below
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        self::$started = true;

        self::enforceTimeouts(
            (int) ($config['lifetime'] ?? 7200),
            (int) ($config['idle_timeout'] ?? 1800)
        );
    }

    /**
     * Enforce absolute and idle timeouts, destroying expired sessions.
     */
    private static function enforceTimeouts(int $lifetime, int $idleTimeout): void
    {
        $now = time();

        if (!isset($_SESSION['__created_at'])) {
            $_SESSION['__created_at'] = $now;
        }

        $absoluteExpired = ($now - (int) $_SESSION['__created_at']) > $lifetime;
        $idleExpired = isset($_SESSION['__last_active'])
            && ($now - (int) $_SESSION['__last_active']) > $idleTimeout;

        if ($absoluteExpired || $idleExpired) {
            self::destroy();
            session_start();
            $_SESSION['__created_at'] = $now;
            $_SESSION['flash']['warning'] = 'Your session expired. Please sign in again.';
        }

        $_SESSION['__last_active'] = $now;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Regenerate the session id, preserving data. Call right after login.
     */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /**
     * Fully destroy the current session.
     */
    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }
}
