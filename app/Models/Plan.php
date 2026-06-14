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

    protected array $fillable = ['name', 'plan_type', 'description', 'amount', 'parameters', 'benefits', 'status'];

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
