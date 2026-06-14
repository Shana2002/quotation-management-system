<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Request
 *
 * Lightweight wrapper around the current HTTP request providing safe
 * accessors for input, method, path and client metadata.
 */
final class Request
{
    private string $method;
    private string $path;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->path   = $this->resolvePath();
    }

    /**
     * Determine the request path relative to the application base,
     * supporting both clean URLs (PATH_INFO / rewrite) and the
     * ?url= fallback used when mod_rewrite is unavailable.
     */
    private function resolvePath(): string
    {
        // Preferred: rewrite passes the path via the 'url' query var.
        if (isset($_GET['url'])) {
            $path = '/' . trim((string) $_GET['url'], '/');
        } else {
            $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $path = $uri;

            // Strip the directory the front controller lives in
            // (e.g. /quotation/public) when not at the web root.
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
            if ($scriptDir !== '/' && $scriptDir !== '' && str_starts_with($path, $scriptDir)) {
                $path = substr($path, strlen($scriptDir));
            }
        }

        $path = '/' . trim($path, '/');

        return $path === '' ? '/' : $path;
    }

    public function method(): string
    {
        // Support method spoofing via _method (for PUT/DELETE from forms).
        if ($this->method === 'POST' && isset($_POST['_method'])) {
            $spoofed = strtoupper((string) $_POST['_method']);
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoofed;
            }
        }

        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /**
     * Retrieve an input value from POST or GET, trimmed when a string.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;

        return is_string($value) ? trim($value) : $value;
    }

    /**
     * Retrieve all request input (POST + GET).
     *
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    /**
     * Retrieve only the given keys from input.
     *
     * @param string[] $keys
     * @return array<string,mixed>
     */
    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->input($key);
        }

        return $out;
    }

    /**
     * An uploaded file array, if present.
     *
     * @return array<string,mixed>|null
     */
    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
