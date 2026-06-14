<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Quotation;
use PDO;

/**
 * ReportService
 *
 * Produces the data sets behind the daily, monthly and employee-performance
 * reports. All queries respect the same per-role scoping as the rest of the
 * app by reusing Quotation::scopeForUser().
 */
final class ReportService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Daily report: every quotation created on a given date + a summary.
     *
     * @param array<string,mixed> $user
     * @return array{rows:array<int,array<string,mixed>>,summary:array<string,mixed>}
     */
    public function daily(array $user, string $date): array
    {
        [$scope, $params] = (new Quotation())->scopeForUser($user);
        $params['d'] = $date;

        $sql = "SELECT q.quotation_number, c.name AS customer_name, u.name AS created_by_name,
                       q.total, q.status, q.created_at
                FROM quotations q
                JOIN customers c ON c.id = q.customer_id
                LEFT JOIN users u ON u.id = q.created_by
                WHERE {$scope} AND DATE(q.created_at) = :d
                ORDER BY q.created_at ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return [
            'rows'    => $rows,
            'summary' => [
                'date'  => $date,
                'count' => count($rows),
                'total' => array_sum(array_map(static fn ($r) => (float) $r['total'], $rows)),
            ],
        ];
    }

    /**
     * Monthly report: per-day totals across a month + a summary.
     *
     * @param array<string,mixed> $user
     * @param string              $month 'Y-m'
     * @return array{rows:array<int,array<string,mixed>>,summary:array<string,mixed>}
     */
    public function monthly(array $user, string $month): array
    {
        [$scope, $params] = (new Quotation())->scopeForUser($user);
        $params['m'] = $month;

        $sql = "SELECT DATE(q.created_at) AS day,
                       COUNT(*) AS count,
                       COALESCE(SUM(q.total), 0) AS total,
                       COALESCE(SUM(CASE WHEN q.status = 'accepted' THEN 1 ELSE 0 END), 0) AS accepted
                FROM quotations q
                WHERE {$scope} AND DATE_FORMAT(q.created_at, '%Y-%m') = :m
                GROUP BY DATE(q.created_at)
                ORDER BY day ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return [
            'rows'    => $rows,
            'summary' => [
                'month' => $month,
                'count' => array_sum(array_map(static fn ($r) => (int) $r['count'], $rows)),
                'total' => array_sum(array_map(static fn ($r) => (float) $r['total'], $rows)),
            ],
        ];
    }

    /**
     * Employee performance: per-user quotation counts and values.
     *
     * @param array<string,mixed> $user
     * @return array<int,array<string,mixed>>
     */
    public function performance(array $user): array
    {
        [$scope, $params] = (new Quotation())->scopeForUser($user);

        $sql = "SELECT u.id, u.name AS employee, r.name AS role,
                       COUNT(q.id) AS total_count,
                       COALESCE(SUM(q.total), 0) AS total_value,
                       COALESCE(SUM(CASE WHEN q.status = 'accepted' THEN 1 ELSE 0 END), 0) AS accepted_count,
                       COALESCE(SUM(CASE WHEN q.status = 'accepted' THEN q.total ELSE 0 END), 0) AS accepted_value
                FROM quotations q
                JOIN users u ON u.id = q.created_by
                JOIN roles r ON r.id = u.role_id
                WHERE {$scope}
                GROUP BY u.id, u.name, r.name
                ORDER BY total_value DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
