<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Response
 *
 * Small helpers for emitting HTTP responses (HTML, JSON, redirects, files).
 */
final class Response
{
    public static function html(string $html, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
    }

    /**
     * @param array<string,mixed>|list<mixed> $data
     */
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    /**
     * Stream a generated PDF (or other binary) to the browser.
     */
    public static function download(string $content, string $filename, string $mime = 'application/pdf'): void
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
    }
}
