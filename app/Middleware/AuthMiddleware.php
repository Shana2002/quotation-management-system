<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;

/**
 * AuthMiddleware
 *
 * Ensures a user is authenticated; otherwise redirects to the login page
 * (remembering the intended destination).
 */
final class AuthMiddleware
{
    public function handle(): void
    {
        if (Auth::check()) {
            return;
        }

        Session::set('intended', $_SERVER['REQUEST_URI'] ?? '/');
        Session::set('flash', ['warning' => 'Please sign in to continue.']);
        Response::redirect(url('/login'));
    }
}
