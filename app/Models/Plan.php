<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Plan model — sellable packages used as quotation line items.
 */
final class Plan extends Model
{
    protected string $table = 'plans';

    /**
     * `benefits` is deliberately absent: the letter no longer carries a
     * Benefits & Conditions block, so nothing writes it. The column and any
     * text already stored stay in the database, and leaving it out of the
     * fillable list means an ordinary plan edit cannot blank it.
     */
    protected array $fillable = [
        'name', 'plan_type', 'description', 'amount', 'parameters',
        'summary_template', 'terms_template', 'status',
    ];

    /**
     * Only active plans (for quotation building dropdowns).
     *
     * @return array<int,array<string,mixed>>
     */
    public function active(): array
    {
        return $this->where(['status' => 'active'], 'name', 'ASC');
    }

    /**
     * Decode a plan row's `parameters` JSON into an array.
     *
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    public static function parameters(array $plan): array
    {
        $decoded = json_decode((string) ($plan['parameters'] ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }
}
