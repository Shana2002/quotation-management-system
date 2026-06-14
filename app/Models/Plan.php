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

    protected array $fillable = ['name', 'description', 'amount', 'status'];

    /**
     * Only active plans (for quotation building dropdowns).
     *
     * @return array<int,array<string,mixed>>
     */
    public function active(): array
    {
        return $this->where(['status' => 'active'], 'name', 'ASC');
    }
}
