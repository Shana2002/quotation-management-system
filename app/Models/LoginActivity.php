<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * LoginActivity model — records every authentication attempt.
 */
final class LoginActivity extends Model
{
    protected string $table = 'login_activity';

    protected array $fillable = [
        'user_id', 'email', 'ip_address', 'user_agent', 'status',
    ];

    /**
     * Persist a login attempt.
     *
     * @param 'success'|'failed' $status
     */
    public function record(?int $userId, string $email, string $ip, string $userAgent, string $status): void
    {
        $this->create([
            'user_id'    => $userId,
            'email'      => $email,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'status'     => $status,
        ]);
    }

    /**
     * Recent login attempts (admin view), most recent first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function recent(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $sql = "SELECT la.*, u.name AS user_name
                FROM login_activity la
                LEFT JOIN users u ON u.id = la.user_id
                ORDER BY la.created_at DESC
                LIMIT {$limit}";

        return $this->db->query($sql)->fetchAll();
    }
}
