<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * View
 *
 * Renders plain-PHP templates inside an optional layout. Templates live in
 * app/Views and receive variables as extracted locals. A child view's output
 * is captured and exposed to the layout as $content.
 */
final class View
{
    private static string $viewPath = '';

    public static function setViewPath(string $path): void
    {
        self::$viewPath = rtrim($path, '/\\');
    }

    /**
     * Render a view (optionally wrapped in a layout) and return the HTML.
     *
     * @param array<string,mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::renderPartial($view, $data);

        if ($layout === null) {
            return $content;
        }

        // Make the rendered child content available to the layout.
        $data['content'] = $content;

        return self::renderPartial($layout, $data);
    }

    /**
     * Render a single template file without a layout.
     *
     * @param array<string,mixed> $data
     */
    public static function renderPartial(string $view, array $data = []): string
    {
        $file = self::$viewPath . '/' . str_replace('.', '/', $view) . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("View not found: {$view} ({$file})");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $file;

        return (string) ob_get_clean();
    }
}
