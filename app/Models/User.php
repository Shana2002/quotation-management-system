<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * User model — admins, managers and executives.
 */
final class User extends Model
{
    protected string $table = 'users';

    protected array $fillable = [
        'role_id', 'name', 'email', 'password_hash', 'phone',
        'status', 'manager_id', 'created_by', 'last_login_at',
    ];

    /**
     * Find a user by email, including their role name.
     *
     * @return array<string,mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT u.*, r.name AS role_name
                FROM users u
                JOIN roles r ON r.id = u.role_id
                WHERE u.email = :email
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Find a user by id, including their role name.
     *
     * @return array<string,mixed>|null
     */
    public function findWithRole(int $id): ?array
    {
        $sql = "SELECT u.*, r.name AS role_name
                FROM users u
                JOIN roles r ON r.id = u.role_id
                WHERE u.id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * List users filtered by role name, optionally scoped to a manager.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listByRole(string $roleName, ?int $managerId = null): array
    {
        $sql = "SELECT u.*, r.name AS role_name, m.name AS manager_name
                FROM users u
                JOIN roles r ON r.id = u.role_id
                LEFT JOIN users m ON m.id = u.manager_id
                WHERE r.name = :role";
        $params = ['role' => $roleName];

        if ($managerId !== null) {
            $sql .= " AND u.manager_id = :mid";
            $params['mid'] = $managerId;
        }

        $sql .= " ORDER BY u.name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Executives belonging to a given manager (id list helper).
     *
     * @return int[]
     */
    public function executiveIdsForManager(int $managerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM users WHERE manager_id = :mid"
        );
        $stmt->execute(['mid' => $managerId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    /**
     * All active managers (for assignment dropdowns).
     *
     * @return array<int,array<string,mixed>>
     */
    public function activeManagers(): array
    {
        return $this->listByRole('manager');
    }

    public function touchLastLogin(int $id): void
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET last_login_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }
}
