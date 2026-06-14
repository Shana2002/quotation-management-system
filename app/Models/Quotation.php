<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Quotation model.
 *
 * Provides role-scoped listing and the statistics used by dashboards and
 * reports. Data scoping rules:
 *   - admin      : all quotations
 *   - manager    : own quotations + those of assigned executives
 *   - executive  : own quotations only
 */
final class Quotation extends Model
{
    protected string $table = 'quotations';

    protected array $fillable = [
        'quotation_number', 'customer_id', 'created_by', 'subtotal', 'discount',
        'tax', 'total', 'notes', 'terms', 'expiry_date', 'status', 'verification_token',
    ];

    /**
     * Build a SQL scope fragment + params for the given user.
     *
     * @param array<string,mixed> $user
     * @return array{0:string,1:array<string,mixed>}  [whereFragment, params]
     */
    public function scopeForUser(array $user): array
    {
        $role = $user['role_name'] ?? 'executive';
        $id   = (int) $user['id'];

        if ($role === 'admin') {
            return ['1=1', []];
        }

        if ($role === 'manager') {
            // Manager sees own + their executives' quotations.
            $execIds = (new User())->executiveIdsForManager($id);
            $ids     = array_merge([$id], $execIds);
            $place   = [];
            $params  = [];
            foreach (array_values($ids) as $i => $uid) {
                $key          = "u{$i}";
                $place[]      = ":{$key}";
                $params[$key] = $uid;
            }
            return ['q.created_by IN (' . implode(',', $place) . ')', $params];
        }

        // Executive: own only.
        return ['q.created_by = :uid', ['uid' => $id]];
    }

    /**
     * List quotations visible to a user, with customer + creator names.
     *
     * @param array<string,mixed> $user
     * @return array<int,array<string,mixed>>
     */
    public function listForUser(array $user): array
    {
        [$scope, $params] = $this->scopeForUser($user);

        $sql = "SELECT q.*, c.name AS customer_name, u.name AS created_by_name
                FROM quotations q
                JOIN customers c ON c.id = q.customer_id
                LEFT JOIN users u ON u.id = q.created_by
                WHERE {$scope}
                ORDER BY q.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * A single quotation with customer and creator details.
     *
     * @return array<string,mixed>|null
     */
    public function findDetailed(int $id): ?array
    {
        $sql = "SELECT q.*, c.name AS customer_name, c.address AS customer_address,
                       c.telephone AS customer_telephone, c.nic AS customer_nic,
                       c.email AS customer_email, u.name AS created_by_name
                FROM quotations q
                JOIN customers c ON c.id = q.customer_id
                LEFT JOIN users u ON u.id = q.created_by
                WHERE q.id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Find a quotation by its public verification token (for QR landing).
     *
     * @return array<string,mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        $sql = "SELECT q.*, c.name AS customer_name
                FROM quotations q
                JOIN customers c ON c.id = q.customer_id
                WHERE q.verification_token = :t
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['t' => $token]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Quotations for a single customer (history).
     *
     * @return array<int,array<string,mixed>>
     */
    public function forCustomer(int $customerId): array
    {
        $sql = "SELECT q.*, u.name AS created_by_name
                FROM quotations q
                LEFT JOIN users u ON u.id = q.created_by
                WHERE q.customer_id = :cid
                ORDER BY q.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cid' => $customerId]);

        return $stmt->fetchAll();
    }

    /**
     * Whether a user is allowed to view a given quotation row.
     *
     * @param array<string,mixed> $user
     * @param array<string,mixed> $quotation
     */
    public function userCanAccess(array $user, array $quotation): bool
    {
        $role = $user['role_name'] ?? 'executive';
        if ($role === 'admin') {
            return true;
        }
        $uid = (int) $user['id'];
        if ($role === 'manager') {
            $execIds = (new User())->executiveIdsForManager($uid);
            return in_array((int) $quotation['created_by'], array_merge([$uid], $execIds), true);
        }
        return (int) $quotation['created_by'] === $uid;
    }

    /* ------------------------------------------------------------------ */
    /*  Statistics (dashboard + reports), all role-scoped                 */
    /* ------------------------------------------------------------------ */

    /**
     * Aggregate stats for a user's visible quotations.
     *
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public function statsForUser(array $user): array
    {
        [$scope, $params] = $this->scopeForUser($user);

        $sql = "SELECT
                    COUNT(*) AS total_count,
                    COALESCE(SUM(q.total), 0) AS total_value,
                    COALESCE(SUM(CASE WHEN DATE(q.created_at) = CURDATE() THEN 1 ELSE 0 END), 0) AS today_count,
                    COALESCE(SUM(CASE WHEN q.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END), 0) AS month_count,
                    COALESCE(SUM(CASE WHEN q.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN q.total ELSE 0 END), 0) AS month_value,
                    COALESCE(SUM(CASE WHEN q.status = 'accepted' THEN 1 ELSE 0 END), 0) AS accepted_count
                FROM quotations q
                WHERE {$scope}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() ?: [];
    }

    /**
     * Count of visible quotations grouped by status.
     *
     * @param array<string,mixed> $user
     * @return array<string,int> status => count
     */
    public function countByStatus(array $user): array
    {
        [$scope, $params] = $this->scopeForUser($user);
        $sql = "SELECT q.status, COUNT(*) AS c
                FROM quotations q
                WHERE {$scope}
                GROUP BY q.status";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['status']] = (int) $row['c'];
        }

        return $out;
    }

    /**
     * Monthly totals for the last N months (for the dashboard chart).
     *
     * @param array<string,mixed> $user
     * @return array<int,array{ym:string,count:int,total:float}>
     */
    public function monthlyTrend(array $user, int $months = 6): array
    {
        [$scope, $params] = $this->scopeForUser($user);
        $months = max(1, min($months, 24));

        $sql = "SELECT DATE_FORMAT(q.created_at, '%Y-%m') AS ym,
                       COUNT(*) AS count, COALESCE(SUM(q.total),0) AS total
                FROM quotations q
                WHERE {$scope}
                  AND q.created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL {$months} MONTH)
                GROUP BY ym
                ORDER BY ym ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(static fn ($r) => [
            'ym'    => $r['ym'],
            'count' => (int) $r['count'],
            'total' => (float) $r['total'],
        ], $stmt->fetchAll());
    }

    /**
     * Most recent visible quotations.
     *
     * @param array<string,mixed> $user
     * @return array<int,array<string,mixed>>
     */
    public function recentForUser(array $user, int $limit = 5): array
    {
        [$scope, $params] = $this->scopeForUser($user);
        $limit = max(1, min($limit, 50));

        $sql = "SELECT q.*, c.name AS customer_name, u.name AS created_by_name
                FROM quotations q
                JOIN customers c ON c.id = q.customer_id
                LEFT JOIN users u ON u.id = q.created_by
                WHERE {$scope}
                ORDER BY q.created_at DESC
                LIMIT {$limit}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
