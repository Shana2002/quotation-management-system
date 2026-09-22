<?php

declare(strict_types=1);

namespace App\PlanTypes;

/**
 * Plant Selling — the company sells plants directly to the customer. Pricing
 * is per plant and depends on the crop type. A projected harvest value per
 * plant is also illustrated.
 *
 * Parameters:
 *   crops : list of ['name','price_per_plant','harvest_value_per_plant']
 */
final class PlantSelling extends AbstractPlanType
{
    public function key(): string
    {
        return 'plant_selling';
    }

    public function label(): string
    {
        return 'Plant Selling';
    }

    public function letterTitle(): string
    {
        return 'Plant Selling — Direct Purchase';
    }

    public function formulaNote(): string
    {
        return 'Total = number of plants × crop unit price. '
            . 'Projected harvest value = number of plants × crop harvest value per plant.';
    }

    public function inputFields(array $params): array
    {
        $crops = [];
        foreach (($params['crops'] ?? []) as $crop) {
            $crops[$crop['name']] = $crop['name'];
        }

        return [
            ['name' => 'crop', 'label' => 'Crop', 'type' => 'select', 'options' => $crops, 'required' => true],
            ['name' => 'quantity', 'label' => 'Number of Plants', 'type' => 'number', 'step' => '1', 'required' => true],
        ];
    }

    public function defaultParameters(): array
    {
        // Placeholder crops/prices — adjust in Settings.
        return [
            'crops' => [
                ['name' => 'Agarwood',   'price_per_plant' => 1500.0, 'harvest_value_per_plant' => 25000.0],
                ['name' => 'Sandalwood', 'price_per_plant' => 2000.0, 'harvest_value_per_plant' => 30000.0],
                ['name' => 'Teak',       'price_per_plant' => 800.0,  'harvest_value_per_plant' => 12000.0],
            ],
        ];
    }

    public function defaultBenefits(): string
    {
        return "• High-quality, nursery-raised plants supplied directly.\n"
            . "• Choice of premium crop varieties.\n"
            . "• Strong projected harvest value per plant at maturity.\n"
            . "• Optional planting and maintenance guidance available.";
    }

    public function defaultSummary(): string
    {
        return "Based on the above quotation, the customer purchases \${quantity} \${crop} plants at \${unit_price} per plant, for a total of \${amount}.\n"
            . "The projected harvest value of the plants at maturity is \${projected_harvest}.";
    }

    public function defaultTerms(): string
    {
        return "The total purchase price is \${amount} for \${quantity} \${crop} plants at \${unit_price} per plant.\n"
            . "Plants supplied are nursery-raised and of the agreed variety.\n"
            . "The projected harvest value of \${projected_harvest} is an estimate and not a guarantee.\n"
            . "Planting and maintenance after hand-over are the customer's responsibility unless separately agreed.\n"
            . "Any applicable taxes, statutory deductions, fees, or other charges shall be handled according to the applicable agreement and regulations.\n"
            . "This quotation is subject to formal acceptance and execution of the relevant purchase agreement.";
    }

    protected function tokenLabels(): array
    {
        return [
            'plan_name'                 => 'Plan name',
            'crop'                      => 'Selected crop',
            'quantity'                  => 'Number of plants',
            'unit_price'                => 'Price per plant',
            'amount'                    => 'Total cost with currency',
            'amount_number'             => 'Total cost, digits only',
            'projected_harvest'         => 'Projected harvest value',
            'harvest_value_per_plant'   => 'Harvest value per plant',
        ];
    }

    public function validate(array $inputs): array
    {
        $errors = [];
        if (($inputs['crop'] ?? '') === '') {
            $errors[] = 'Please select a crop.';
        }
        if ($this->int($inputs['quantity'] ?? 0) <= 0) {
            $errors[] = 'Number of plants must be greater than zero.';
        }
        return $errors;
    }

    public function compute(array $inputs, array $params): array
    {
        $cropName = (string) ($inputs['crop'] ?? '');
        $qty      = $this->int($inputs['quantity'] ?? 0);
        $crop     = $this->findCrop($params['crops'] ?? [], $cropName);

        $unitPrice      = (float) ($crop['price_per_plant'] ?? 0);
        $harvestPerPlant = (float) ($crop['harvest_value_per_plant'] ?? 0);
        $total          = $qty * $unitPrice;
        $projectedValue = $qty * $harvestPerPlant;

        $tokens = [
            'plan_name'               => $this->label(),
            'crop'                    => $cropName,
            'quantity'                => number_format($qty),
            'unit_price'              => $this->fmt($unitPrice),
            'amount'                  => $this->fmt($total),
            'amount_number'           => number_format($total, 2, '.', ''),
            'projected_harvest'       => $this->fmt($projectedValue),
            'harvest_value_per_plant' => $this->fmt($harvestPerPlant),
        ];

        return [
            'intro'   => 'Plant Selling — direct purchase of ' . $cropName . ' plants is illustrated below.',
            'details' => $this->details([
                [
                    'title' => 'Order Details',
                    'rows'  => [
                        'Investment Plan'  => $this->label(),
                        'Crop'             => $cropName,
                        'Number of Plants' => number_format($qty),
                        'Unit Price'       => $this->fmt($unitPrice),
                    ],
                ],
                [
                    'title' => 'Investment',
                    'rows'  => [
                        'Total Cost'              => $this->fmt($total),
                        'Projected Harvest Value' => $this->fmt($projectedValue),
                    ],
                ],
            ]),
            'headers' => ['Crop', 'No. of Plants', 'Unit Price', 'Total', 'Projected Harvest Value'],
            'rows'    => [[
                $cropName,
                number_format($qty),
                $this->fmt($unitPrice),
                $this->fmt($total),
                $this->fmt($projectedValue),
            ]],
            'summary' => [
                'Total cost'              => $this->fmt($total),
                'Projected harvest value' => $this->fmt($projectedValue),
            ],
            'tokens'          => $tokens,
            'headline_amount' => $total,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $crops
     * @return array<string,mixed>
     */
    private function findCrop(array $crops, string $name): array
    {
        foreach ($crops as $crop) {
            if (($crop['name'] ?? '') === $name) {
                return $crop;
            }
        }
        return $crops[0] ?? [];
    }
}
