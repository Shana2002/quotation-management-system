<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Flash
 *
 * One-request "flash" messages stored in the session and consumed on the
 * next page render (e.g. after a redirect following a form submission).
 */
final class Flash
{
    private const KEY = 'flash';

    /**
     * Queue a flash message.
     *
     * @param string $type One of: success, danger, warning, info.
     */
    public static function set(string $type, string $message): void
    {
        $_SESSION[self::KEY][$type] = $message;
    }

    public static function success(string $message): void
    {
        self::set('success', $message);
    }

    public static function error(string $message): void
    {
        self::set('danger', $message);
    }

    public static function warning(string $message): void
    {
        self::set('warning', $message);
    }

    public static function info(string $message): void
    {
        self::set('info', $message);
    }

    /**
     * Retrieve and clear all queued messages.
     *
     * @return array<string,string>
     */
    public static function pull(): array
    {
        $messages = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);

        return $messages;
    }
}
