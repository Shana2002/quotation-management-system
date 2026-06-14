<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\LoginActivity;
use App\Models\User;

/**
 * Auth
 *
 * Authentication + authorisation service:
 *  - credential verification with bcrypt (password_verify)
 *  - login/logout with session id regeneration
 *  - role-based access checks
 *  - login attempt tracking (success and failure)
 */
final class Auth
{
    private const SESSION_USER = 'auth_user';

    /**
     * Attempt to authenticate a user by email + password.
     * Records the attempt in login_activity either way.
     */
    public static function attempt(string $email, string $password, string $ip, string $userAgent): bool
    {
        $userModel = new User();
        $user = $userModel->findByEmail($email);

        $loginLog = new LoginActivity();

        $valid = $user !== null
            && $user['status'] === 'active'
            && password_verify($password, $user['password_hash']);

        if (!$valid) {
            $loginLog->record($user['id'] ?? null, $email, $ip, $userAgent, 'failed');
            return false;
        }

        // Transparently upgrade legacy hashes if the algorithm changed.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $userModel->update((int) $user['id'], [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        }

        Session::regenerate();
        self::setUser($user);
        $userModel->touchLastLogin((int) $user['id']);
        $loginLog->record((int) $user['id'], $email, $ip, $userAgent, 'success');

        return true;
    }

    /**
     * Persist the authenticated user in the session (sans password hash).
     *
     * @param array<string,mixed> $user
     */
    public static function setUser(array $user): void
    {
        unset($user['password_hash']);
        Session::set(self::SESSION_USER, $user);
    }

    public static function logout(): void
    {
        Session::remove(self::SESSION_USER);
        Session::destroy();
    }

    public static function check(): bool
    {
        return Session::has(self::SESSION_USER);
    }

    /**
     * The current authenticated user, or null.
     *
     * @return array<string,mixed>|null
     */
    public static function user(): ?array
    {
        return Session::get(self::SESSION_USER);
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user !== null ? (int) $user['id'] : null;
    }

    /**
     * The current user's role name (admin|manager|executive).
     */
    public static function role(): ?string
    {
        return self::user()['role_name'] ?? null;
    }

    public static function hasRole(string ...$roles): bool
    {
        $current = self::role();
        return $current !== null && in_array($current, $roles, true);
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isManager(): bool
    {
        return self::role() === 'manager';
    }

    public static function isExecutive(): bool
    {
        return self::role() === 'executive';
    }
}
