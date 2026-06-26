<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Session;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;

/**
 * UserController — manage managers and executives.
 *
 * Scoping:
 *   - Admin   : manage all managers and executives.
 *   - Manager : manage only their own executives.
 */
final class UserController extends Controller
{
    public function index(): void
    {
        $userModel = new User();

        if (Auth::isAdmin()) {
            $managers   = $userModel->listByRole('manager');
            $executives = $userModel->listByRole('executive');
        } else {
            $managers   = [];
            $executives = $userModel->listByRole('executive', Auth::id());
        }

        $this->view('users/index', [
            'title'      => Auth::isAdmin() ? 'Managers & Executives' : 'My Executives',
            'managers'   => $managers,
            'executives' => $executives,
        ]);
    }

    public function create(): void
    {
        $this->view('users/form', [
            'title'    => 'New User',
            'user'     => null,
            'managers' => (new User())->activeManagers(),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $data = $this->validateUser(null);
        if ($data === null) {
            $this->back('/users/create');
            return;
        }

        $data['created_by'] = Auth::id();
        $id = (new User())->create($data);
        ActivityLog::log('create', 'user', $id, 'Created ' . $this->roleNameFor($data['role_id']) . ': ' . $data['name']);
        Flash::success('User created successfully.');
        $this->redirect('/users');
    }

    public function edit(string $id): void
    {
        $user = (new User())->findWithRole((int) $id);
        if ($user === null || !$this->canManage($user)) {
            Flash::error('User not found or not accessible.');
            $this->redirect('/users');
            return;
        }

        $this->view('users/form', [
            'title'    => 'Edit User',
            'user'     => $user,
            'managers' => (new User())->activeManagers(),
        ]);
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $userModel = new User();
        $user = $userModel->findWithRole((int) $id);
        if ($user === null || !$this->canManage($user)) {
            Flash::error('User not found or not accessible.');
            $this->redirect('/users');
            return;
        }

        $data = $this->validateUser((int) $id);
        if ($data === null) {
            $this->back('/users/' . (int) $id . '/edit');
            return;
        }

        $userModel->update((int) $id, $data);
        ActivityLog::log('update', 'user', (int) $id, 'Updated user: ' . $data['name']);
        Flash::success('User updated successfully.');
        $this->redirect('/users');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $user = (new User())->findWithRole((int) $id);
        if ($user === null || !$this->canManage($user)) {
            Flash::error('User not found or not accessible.');
            $this->redirect('/users');
            return;
        }
        if ((int) $id === Auth::id()) {
            Flash::error('You cannot delete your own account.');
            $this->redirect('/users');
            return;
        }

        try {
            (new User())->delete((int) $id);
            ActivityLog::log('delete', 'user', (int) $id, 'Deleted user: ' . $user['name']);
            Flash::success('User deleted.');
        } catch (\Throwable $e) {
            Flash::error('Cannot delete this user because they have related records.');
        }
        $this->redirect('/users');
    }

    /* ------------------------------------------------------------------ */

    /**
     * Whether the current actor may manage the target user.
     *
     * @param array<string,mixed> $user
     */
    private function canManage(array $user): bool
    {
        if (Auth::isAdmin()) {
            return $user['role_name'] !== 'admin' || (int) $user['id'] === Auth::id();
        }
        // Managers may only manage their own executives.
        return $user['role_name'] === 'executive' && (int) $user['manager_id'] === Auth::id();
    }

    /**
     * Validate and shape user input.
     *
     * @return array<string,mixed>|null
     */
    private function validateUser(?int $ignoreId): ?array
    {
        $input = $this->request->only(['name', 'email', 'phone', 'password', 'role','position', 'manager_id', 'status']);

        // Managers can only create executives assigned to themselves.
        if (Auth::isManager()) {
            $input['role']       = 'executive';
            $input['manager_id'] = (string) Auth::id();
        }

        $emailUnique = 'required|email|max:190|unique:users,email';
        if ($ignoreId !== null) {
            $emailUnique .= ',' . $ignoreId;
        }

        $rules = [
            'name'  => 'required|max:150',
            'email' => $emailUnique,
            'role'  => 'required|in:admin,manager,executive',
        ];
        // Password required on create only.
        if ($ignoreId === null) {
            $rules['password'] = 'required|min:8';
        } elseif (!empty($input['password'])) {
            $rules['password'] = 'min:8';
        }

        $validator = new Validator($input, $rules);
        if ($validator->fails()) {
            Session::set('errors', $validator->flatErrors());
            Session::set('old', $input);
            return null;
        }

        $roleId = (new Role())->idByName((string) $input['role']);

        $data = [
            'name'    => $input['name'],
            'email'   => $input['email'],
            'phone'   => $input['phone'] ?? '',
            'position' => $input['position'] ?? '',
            'role_id' => $roleId,
            'status'  => in_array($input['status'] ?? 'active', ['active', 'inactive'], true) ? $input['status'] : 'active',
            // Only executives get a manager; null for others.
            'manager_id' => ($input['role'] === 'executive' && !empty($input['manager_id']))
                ? (int) $input['manager_id']
                : null,
        ];

        if (!empty($input['password'])) {
            $data['password_hash'] = password_hash((string) $input['password'], PASSWORD_DEFAULT);
        }

        return $data;
    }

    private function roleNameFor(?int $roleId): string
    {
        $role = $roleId !== null ? (new Role())->find($roleId) : null;
        return $role['name'] ?? 'user';
    }
}
