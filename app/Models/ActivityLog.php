<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;

/**
 * ActivityLog model — application-wide audit trail.
 */
final class ActivityLog extends Model
{
    protected string $table = 'activity_logs';

    protected array $fillable = [
        'user_id', 'action', 'entity_type', 'entity_id',
        'description', 'ip_address', 'user_agent',
    ];

    /**
     * Convenience logger that auto-fills the current user and request meta.
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null
    ): void {
        (new self())->create([
            'user_id'     => Auth::id(),
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'description' => $description,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'  => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    }

    /**
     * Paginated audit log with the acting user's name.
     *
     * @return array<int,array<string,mixed>>
     */
    public function paginate(int $limit, int $offset): array
    {
        $limit  = max(1, min($limit, 200));
        $offset = max(0, $offset);
        $sql = "SELECT al.*, u.name AS user_name
                FROM activity_logs al
                LEFT JOIN users u ON u.id = al.user_id
                ORDER BY al.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        return $this->db->query($sql)->fetchAll();
    }
}
