<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Database
 *
 * Thin singleton wrapper around a single shared PDO connection.
 * All data access in the application goes through this connection,
 * guaranteeing that prepared statements are used everywhere.
 */
final class Database
{
    private static ?PDO $instance = null;

    /**
     * Private constructor — use Database::connection().
     */
    private function __construct()
    {
    }

    /**
     * Return the shared PDO connection, creating it on first use.
     */
    public static function connection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $config = require dirname(__DIR__, 2) . '/config/database.php';

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            self::$instance = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );
        } catch (PDOException $e) {
            // Avoid leaking credentials; surface a clean message.
            throw new RuntimeException(
                'Database connection failed: ' . $e->getMessage(),
                (int) $e->getCode()
            );
        }

        return self::$instance;
    }

    /**
     * Reset the connection (primarily for tests).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
