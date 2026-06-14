<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Session;
use App\Core\Validator;
use App\Models\ActivityLog;

/**
 * AuthController — login / logout.
 */
final class AuthController extends Controller
{
    /**
     * Show the login form (or redirect if already authenticated).
     */
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
            return;
        }

        $this->view('auth/login', ['title' => 'Sign In'], 'layouts/auth');
    }

    /**
     * Handle a login attempt.
     */
    public function login(): void
    {
        $this->verifyCsrf();

        $data = $this->request->only(['email', 'password']);
        $validator = new Validator($data, [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            $this->back('/login', $validator->flatErrors(), ['email' => $data['email']]);
            return;
        }

        $ok = Auth::attempt(
            (string) $data['email'],
            (string) $data['password'],
            $this->request->ip(),
            $this->request->userAgent()
        );

        if (!$ok) {
            Flash::error('Invalid credentials or inactive account.');
            $this->back('/login', [], ['email' => $data['email']]);
            return;
        }

        ActivityLog::log('login', 'user', Auth::id(), 'User signed in');

        Session::remove('intended');
        Flash::success('Welcome back, ' . (Auth::user()['name'] ?? '') . '!');

        Csrf::token(); // ensure a fresh token exists post-login
        $this->redirect('/dashboard');
    }

    /**
     * Log the current user out.
     */
    public function logout(): void
    {
        $this->verifyCsrf();
        ActivityLog::log('logout', 'user', Auth::id(), 'User signed out');
        Auth::logout();
        Flash::success('You have been signed out.');
        $this->redirect('/login');
    }
}
