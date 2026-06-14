<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Controller
 *
 * Base controller providing view rendering, redirects, JSON responses and
 * authorisation helpers shared by all concrete controllers.
 */
abstract class Controller
{
    protected Request $request;

    public function __construct()
    {
        $this->request = new Request();
    }

    /**
     * Render a view within the default layout and emit it.
     *
     * @param array<string,mixed> $data
     */
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        Response::html(View::render($view, $data, $layout));
    }

    /**
     * @param array<string,mixed> $data
     */
    protected function json(array $data, int $status = 200): void
    {
        Response::json($data, $status);
    }

    protected function redirect(string $path): void
    {
        Response::redirect(url($path));
    }

    /**
     * Redirect back with a one-off validation error bag and old input.
     *
     * @param string[]            $errors
     * @param array<string,mixed> $old
     */
    protected function back(string $path, array $errors = [], array $old = []): void
    {
        if ($errors !== []) {
            Session::set('errors', $errors);
        }
        if ($old !== []) {
            // Never echo passwords back to the form.
            unset($old['password'], $old['password_confirmation']);
            Session::set('old', $old);
        }
        $this->redirect($path);
    }

    /**
     * The current authenticated user.
     *
     * @return array<string,mixed>|null
     */
    protected function user(): ?array
    {
        return Auth::user();
    }

    /**
     * Abort with 403 unless the current user has one of the given roles.
     */
    protected function authorize(string ...$roles): void
    {
        if (!Auth::hasRole(...$roles)) {
            Response::html(View::render('errors/403', [], 'layouts/app'), 403);
            exit;
        }
    }

    /**
     * Validate the CSRF token of the current request or abort with 419.
     */
    protected function verifyCsrf(): void
    {
        $token = $this->request->input('_csrf');
        if (!Csrf::validate(is_string($token) ? $token : null)) {
            Response::html(View::render('errors/419', [], 'layouts/app'), 419);
            exit;
        }
    }
}
