<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Response;
use App\Core\View;

/**
 * RoleMiddleware
 *
 * Authorises the current user against a comma-separated list of allowed
 * roles, e.g. 'role:admin' or 'role:admin,manager'. Assumes AuthMiddleware
 * has already guaranteed authentication.
 */
final class RoleMiddleware
{
    public function handle(string $roles): void
    {
        $allowed = array_map('trim', explode(',', $roles));

        if (Auth::hasRole(...$allowed)) {
            return;
        }

        Response::html(View::render('errors/403', [], 'layouts/app'), 403);
        exit;
    }
}
