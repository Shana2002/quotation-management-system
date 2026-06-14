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
        $total          = $qty * $unitPrice;
        $projectedValue = $qty * (float) ($crop['harvest_value_per_plant'] ?? 0);

        return [
            'intro'   => 'Plant Selling — direct purchase of ' . $cropName . ' plants is illustrated below.',
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
