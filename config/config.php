<?php
/**
 * Application configuration.
 *
 * Values can be overridden by environment variables (useful on HestiaCP),
 * falling back to sensible local-XAMPP defaults.
 */

declare(strict_types=1);

/**
 * Small helper to read an environment variable with a default.
 *
 * Guarded so this file can be loaded more than once (App bootstrap and the
 * config() helper both read it) without redeclaring the function.
 */
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        // Normalise common boolean-ish strings.
        return match (strtolower($value)) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            default => $value,
        };
    }
}

return [
    // Human-readable application name.
    'app_name' => env('APP_NAME', 'Quotation Management System'),

    // 'production' disables verbose error output.
    'env' => env('APP_ENV', 'local'),

    // Show detailed errors only outside production.
    'debug' => env('APP_DEBUG', true),

    /*
     * Base URL of the application WITHOUT a trailing slash.
     *
     * Local XAMPP (docroot = project's public/ served at root):
     *   http://localhost:8000
     * Local XAMPP (served from htdocs):
     *   http://localhost/quotation/public
     * Production (HestiaCP, docroot pointed at public/):
     *   https://your-domain.com
     *
     * Leave empty ('') to let the app auto-detect from the request.
     */
    'base_url' => env('APP_URL', ''),

    // Default timezone for dates/timestamps.
    'timezone' => env('APP_TIMEZONE', 'Asia/Colombo'),

    // Currency formatting.
    'currency_symbol' => env('APP_CURRENCY', 'Rs.'),

    // Session configuration.
    'session' => [
        'name'         => 'QMS_SESSION',
        'lifetime'     => 60 * 60 * 2, // 2 hours absolute
        'idle_timeout' => 60 * 30,     // 30 minutes of inactivity
    ],

    // Secure file upload constraints (logo, etc.).
    'uploads' => [
        'max_size'      => 2 * 1024 * 1024, // 2 MB
        'allowed_mimes' => ['image/png', 'image/jpeg', 'image/gif', 'image/webp'],
        'allowed_ext'   => ['png', 'jpg', 'jpeg', 'gif', 'webp'],
        'path'          => __DIR__ . '/../public/assets/uploads',
    ],
];
