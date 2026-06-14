<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Customer model.
 */
final class Customer extends Model
{
    protected string $table = 'customers';

    protected array $fillable = [
        'name', 'address', 'telephone', 'nic', 'email', 'created_by',
    ];

    /**
     * All customers with their creator's name, newest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function allWithCreator(): array
    {
        $sql = "SELECT c.*, u.name AS created_by_name
                FROM customers c
                LEFT JOIN users u ON u.id = c.created_by
                ORDER BY c.name ASC";

        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Search customers by name, NIC, email or telephone.
     *
     * @return array<int,array<string,mixed>>
     */
    public function search(string $term): array
    {
        $like = '%' . $term . '%';
        $sql = "SELECT c.*, u.name AS created_by_name
                FROM customers c
                LEFT JOIN users u ON u.id = c.created_by
                WHERE c.name LIKE :t OR c.nic LIKE :t2 OR c.email LIKE :t3 OR c.telephone LIKE :t4
                ORDER BY c.name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['t' => $like, 't2' => $like, 't3' => $like, 't4' => $like]);

        return $stmt->fetchAll();
    }
}
