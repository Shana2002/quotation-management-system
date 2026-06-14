<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;

/*
 * Global helper functions.
 *
 * These are intentionally simple, framework-style helpers used throughout
 * controllers and views to keep templates readable and output safe.
 */

if (!function_exists('config')) {
    /**
     * Read a value from the loaded application config (dot notation).
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $config = null;
        if ($config === null) {
            $config = require dirname(__DIR__, 2) . '/config/config.php';
        }

        $segments = explode('.', $key);
        $value = $config;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('base_url')) {
    /**
     * Resolve the application base URL, auto-detecting when not configured.
     */
    function base_url(): string
    {
        static $base = null;
        if ($base !== null) {
            return $base;
        }

        $configured = (string) config('base_url', '');
        if ($configured !== '') {
            return $base = rtrim($configured, '/');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // Directory the front controller is served from (e.g. /quotation/public).
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $scriptDir = rtrim($scriptDir, '/');

        return $base = $scheme . '://' . $host . $scriptDir;
    }
}

if (!function_exists('url')) {
    /**
     * Build an absolute application URL for a path.
     */
    function url(string $path = '/'): string
    {
        return base_url() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * Build a URL to a public asset.
     */
    function asset(string $path): string
    {
        return base_url() . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('e')) {
    /**
     * HTML-escape a value for safe output (XSS prevention).
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Render the hidden CSRF token input.
     */
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('method_field')) {
    /**
     * Render a hidden field to spoof PUT/PATCH/DELETE from a form.
     */
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve previously submitted input after a validation redirect.
     */
    function old(string $key, mixed $default = ''): mixed
    {
        $old = Session::get('old', []);
        return $old[$key] ?? $default;
    }
}

if (!function_exists('errors')) {
    /**
     * Retrieve the validation error bag from the last request.
     *
     * @return string[]
     */
    function errors(): array
    {
        return (array) Session::get('errors', []);
    }
}

if (!function_exists('money')) {
    /**
     * Format a monetary amount with the configured currency symbol.
     */
    function money(float|int|string $amount): string
    {
        $symbol = (string) config('currency_symbol', 'Rs.');
        return $symbol . ' ' . number_format((float) $amount, 2);
    }
}

if (!function_exists('auth')) {
    /**
     * Current authenticated user array (or null).
     *
     * @return array<string,mixed>|null
     */
    function auth(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('can')) {
    /**
     * Check whether the current user holds one of the given roles.
     */
    function can(string ...$roles): bool
    {
        return Auth::hasRole(...$roles);
    }
}

if (!function_exists('active_class')) {
    /**
     * Return 'active' when the current request path matches a prefix.
     */
    function active_class(string $prefix, string $class = 'active'): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return str_contains($path, $prefix) ? $class : '';
    }
}

if (!function_exists('format_date')) {
    /**
     * Format a date/datetime string for display; returns '—' when empty.
     */
    function format_date(?string $value, string $format = 'd M Y'): string
    {
        if (empty($value)) {
            return '—';
        }
        $ts = strtotime($value);
        return $ts === false ? e($value) : date($format, $ts);
    }
}

if (!function_exists('status_badge')) {
    /**
     * Map a status string to a Bootstrap badge class.
     */
    function status_badge(string $status): string
    {
        return match (strtolower($status)) {
            'active', 'accepted', 'success' => 'success',
            'inactive', 'rejected', 'failed' => 'danger',
            'sent', 'info'                  => 'info',
            'draft'                         => 'secondary',
            'expired', 'warning'            => 'warning',
            default                         => 'secondary',
        };
    }
}
