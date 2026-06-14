<?php

declare(strict_types=1);

/**
 * Front controller.
 *
 * Single entry point for the entire application. All requests are routed here
 * (via .htaccess rewrite, or the ?url= fallback) and dispatched by App.
 */

use App\Core\App;

$basePath = dirname(__DIR__);

require $basePath . '/app/Core/App.php';

(new App($basePath))->run();
