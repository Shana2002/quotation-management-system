<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * QuotationItem model — individual line items belonging to a quotation.
 */
final class QuotationItem extends Model
{
    protected string $table = 'quotation_items';

    protected array $fillable = [
        'quotation_id', 'plan_id', 'description', 'quantity', 'unit_price', 'line_total',
    ];

    /**
     * All line items for a quotation.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forQuotation(int $quotationId): array
    {
        return $this->where(['quotation_id' => $quotationId], 'id', 'ASC');
    }

    /**
     * Replace all items for a quotation with a fresh set.
     *
     * @param array<int,array<string,mixed>> $items
     */
    public function replaceForQuotation(int $quotationId, array $items): void
    {
        $del = $this->db->prepare("DELETE FROM quotation_items WHERE quotation_id = :id");
        $del->execute(['id' => $quotationId]);

        foreach ($items as $item) {
            $item['quotation_id'] = $quotationId;
            $this->create($item);
        }
    }
}
