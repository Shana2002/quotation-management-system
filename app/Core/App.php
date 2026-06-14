<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * App
 *
 * Application bootstrapper: registers the autoloader, loads configuration and
 * helpers, starts the session, wires the router and dispatches the request.
 */
final class App
{
    private array $config;
    private Router $router;

    public function __construct(private string $basePath)
    {
        $this->registerAutoloader();
        $this->config = require $this->basePath . '/config/config.php';

        require_once $this->basePath . '/app/Helpers/functions.php';

        date_default_timezone_set($this->config['timezone'] ?? 'UTC');
        $this->configureErrorReporting();

        View::setViewPath($this->basePath . '/app/Views');
        Session::start($this->config['session']);

        $this->router = new Router();
        $this->loadRoutes();
    }

    /**
     * PSR-4-style autoloader for the App\ namespace.
     */
    private function registerAutoloader(): void
    {
        spl_autoload_register(function (string $class): void {
            $prefix  = 'App\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = $this->basePath . '/app/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }

    private function configureErrorReporting(): void
    {
        $debug = (bool) ($this->config['debug'] ?? false)
            && ($this->config['env'] ?? 'production') !== 'production';

        if ($debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }
    }

    private function loadRoutes(): void
    {
        $router = $this->router; // exposed to the included file
        require $this->basePath . '/routes/web.php';
    }

    /**
     * Run the application: send security headers and dispatch the request.
     */
    public function run(): void
    {
        $this->sendSecurityHeaders();

        $request = new Request();

        try {
            $matched = $this->router->dispatch($request);
            if (!$matched) {
                Response::html(View::render('errors/404', [], 'layouts/app'), 404);
            }
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    private function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 1; mode=block');
        // Permissive CSP that still allows the documented CDN assets.
        header(
            "Content-Security-Policy: default-src 'self'; "
            . "img-src 'self' data:; "
            . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
            . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
            . "font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com data:;"
        );
    }

    private function handleException(Throwable $e): void
    {
        $debug = (bool) ($this->config['debug'] ?? false)
            && ($this->config['env'] ?? 'production') !== 'production';

        if ($debug) {
            Response::html(
                '<h1>Application Error</h1>'
                . '<p><strong>' . e($e->getMessage()) . '</strong></p>'
                . '<pre>' . e($e->getTraceAsString()) . '</pre>',
                500
            );
            return;
        }

        error_log('[QMS] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        Response::html(View::render('errors/500', [], 'layouts/app'), 500);
    }
}
