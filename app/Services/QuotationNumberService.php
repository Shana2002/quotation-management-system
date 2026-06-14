<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Setting;

/**
 * QuotationNumberService
 *
 * Generates sequential, human-readable quotation numbers of the form
 * {PREFIX}-{YYYYMM}-{NNNN}, e.g. QTN-202606-0007. The sequence resets per
 * calendar month and is derived from the highest existing number for that
 * month, guaranteeing uniqueness even with the DB-level unique constraint
 * as a final safety net.
 */
final class QuotationNumberService
{
    /**
     * Produce the next quotation number.
     */
    public function next(): string
    {
        $prefix = (new Setting())->get('quotation_prefix', 'QTN');
        $prefix = preg_replace('/[^A-Za-z0-9]/', '', $prefix) ?: 'QTN';
        $period = date('Ym');
        $like   = "{$prefix}-{$period}-%";

        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT quotation_number
             FROM quotations
             WHERE quotation_number LIKE :like
             ORDER BY quotation_number DESC
             LIMIT 1"
        );
        $stmt->execute(['like' => $like]);
        $last = $stmt->fetchColumn();

        $sequence = 1;
        if (is_string($last)) {
            $parts = explode('-', $last);
            $sequence = ((int) end($parts)) + 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $period, $sequence);
    }

    /**
     * Generate a cryptographically-random public verification token.
     */
    public function token(): string
    {
        return bin2hex(random_bytes(32));
    }
}
