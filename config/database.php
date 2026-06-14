<?php
/**
 * Database connection configuration.
 *
 * Credentials are read from environment variables when present (recommended on
 * HestiaCP / production) and otherwise fall back to default XAMPP values.
 *
 * For local overrides without editing this file, you may create
 * config/database.local.php returning an array that is merged on top.
 */

declare(strict_types=1);

$config = [
    'driver'   => getenv('DB_DRIVER') ?: 'mysql',
    'host'     => getenv('DB_HOST') ?: '127.0.0.1',
    'port'     => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_NAME') ?: 'qms',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
    'charset'  => 'utf8mb4',

    // PDO options applied to every connection.
    'options' => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // use real prepared statements
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ],
];

// Optional local override file (git-ignored).
$localFile = __DIR__ . '/database.local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        $config = array_replace($config, $local);
    }
}

return $config;
