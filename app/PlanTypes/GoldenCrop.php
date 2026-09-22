<?php

declare(strict_types=1);

namespace App\PlanTypes;

/**
 * Golden Crop — the company plants a chosen crop on the customer's bare land.
 * Pricing is per 10 perches and depends on the crop. A projected harvest
 * income per 10 perches is also illustrated.
 *
 * Parameters:
 *   crops : list of ['name','price_per_10perch','harvest_value_per_10perch']
 */
final class GoldenCrop extends AbstractPlanType
{
    public function key(): string
    {
        return 'golden_crop';
    }

    public function label(): string
    {
        return 'Golden Crop';
    }

    public function letterTitle(): string
    {
        return 'Golden Crop — Plantation on Your Land';
    }

    public function formulaNote(): string
    {
        return 'Units = land perches ÷ 10. Investment = units × crop price per 10 perches. '
            . 'Projected harvest income = units × crop harvest value per 10 perches.';
    }

    public function inputFields(array $params): array
    {
        $crops = [];
        foreach (($params['crops'] ?? []) as $crop) {
            $crops[$crop['name']] = $crop['name'];
        }

        return [
            ['name' => 'crop', 'label' => 'Crop', 'type' => 'select', 'options' => $crops, 'required' => true],
            ['name' => 'land_perches', 'label' => 'Land Extent (Perches)', 'type' => 'number', 'step' => '0.01', 'required' => true],
        ];
    }

    public function defaultParameters(): array
    {
        // Placeholder crops/prices — adjust in Settings.
        return [
            'crops' => [
                ['name' => 'Agarwood',   'price_per_10perch' => 250000.0, 'harvest_value_per_10perch' => 1500000.0],
                ['name' => 'Sandalwood', 'price_per_10perch' => 300000.0, 'harvest_value_per_10perch' => 1800000.0],
                ['name' => 'Teak',       'price_per_10perch' => 150000.0, 'harvest_value_per_10perch' => 900000.0],
            ],
        ];
    }

    public function defaultBenefits(): string
    {
        return "• The company plants and maintains the crop on your own land.\n"
            . "• You retain full ownership of the land and the trees.\n"
            . "• Significant projected harvest income at maturity.\n"
            . "• Professional plantation management throughout the growth cycle.";
    }

    public function defaultSummary(): string
    {
        return "Based on the above quotation, the customer invests \${amount} to plant \${crop} on \${land_extent} of land under the \${plan_name} plan.\n"
            . "The company carries out planting and maintenance on the customer's own land.\n"
            . "The projected harvest income at maturity is \${projected_harvest}.";
    }

    public function defaultTerms(): string
    {
        return "The plantation term runs from the agreed commencement date through to harvest maturity.\n"
            . "The investment amount of \${amount} covers planting and maintenance of \${crop} on \${land_extent}.\n"
            . "The company shall plant and maintain the crop on the customer's land for the agreed term.\n"
            . "Ownership of the land and the standing trees remains with the customer.\n"
            . "The projected harvest income of \${projected_harvest} is an estimate and not a guarantee.\n"
            . "This quotation is subject to formal acceptance and execution of the relevant plantation agreement.";
    }

    protected function tokenLabels(): array
    {
        return [
            'plan_name'                  => 'Plan name',
            'crop'                       => 'Selected crop',
            'land_extent'                => 'Land extent, e.g. "20 Perches"',
            'land_perches'               => 'Land extent in perches, digits only',
            'units'                      => 'Number of 10-perch blocks',
            'amount'                     => 'Investment amount with currency',
            'amount_number'              => 'Investment amount, digits only',
            'projected_harvest'          => 'Projected harvest income',
            'price_per_10perch'          => 'Crop price per 10 perches',
            'harvest_value_per_10perch'  => 'Harvest value per 10 perches',
        ];
    }

    public function validate(array $inputs): array
    {
        $errors = [];
        if (($inputs['crop'] ?? '') === '') {
            $errors[] = 'Please select a crop.';
        }
        if ($this->num($inputs['land_perches'] ?? 0) <= 0) {
            $errors[] = 'Land extent must be greater than zero.';
        }
        return $errors;
    }

    public function compute(array $inputs, array $params): array
    {
        $cropName = (string) ($inputs['crop'] ?? '');
        $perches  = $this->num($inputs['land_perches'] ?? 0);
        $crop     = $this->findCrop($params['crops'] ?? [], $cropName);

        $units            = $perches / 10;
        $pricePer10       = (float) ($crop['price_per_10perch'] ?? 0);
        $harvestPer10     = (float) ($crop['harvest_value_per_10perch'] ?? 0);
        $investment       = $units * $pricePer10;
        $projectedHarvest = $units * $harvestPer10;

        $extentLabel = rtrim(rtrim(number_format($perches, 2), '0'), '.') . ' Perches';

        $tokens = [
            'plan_name'                 => $this->label(),
            'crop'                      => $cropName,
            'land_extent'               => $extentLabel,
            'land_perches'              => rtrim(rtrim(number_format($perches, 2, '.', ''), '0'), '.'),
            'units'                     => rtrim(rtrim(number_format($units, 2, '.', ''), '0'), '.'),
            'amount'                    => $this->fmt($investment),
            'amount_number'             => number_format($investment, 2, '.', ''),
            'projected_harvest'         => $this->fmt($projectedHarvest),
            'price_per_10perch'         => $this->fmt($pricePer10),
            'harvest_value_per_10perch' => $this->fmt($harvestPer10),
        ];

        return [
            'intro'   => 'Golden Crop plan — planting ' . $cropName . ' on your land is illustrated below.',
            'details' => $this->details([
                [
                    'title' => 'Plantation Plan',
                    'rows'  => [
                        'Investment Plan'          => $this->label(),
                        'Crop'                     => $cropName,
                        'Land Extent'              => $extentLabel,
                        'Units (per 10 Perches)'   => rtrim(rtrim(number_format($units, 2), '0'), '.'),
                    ],
                ],
                [
                    'title' => 'Investment',
                    'rows'  => [
                        'Investment (Planting Cost)' => $this->fmt($investment),
                        'Projected Harvest Income'   => $this->fmt($projectedHarvest),
                    ],
                ],
            ]),
            'headers' => ['Crop', 'Land Extent', 'Investment', 'Projected Harvest Income'],
            'rows'    => [[
                $cropName,
                $extentLabel,
                $this->fmt($investment),
                $this->fmt($projectedHarvest),
            ]],
            'summary' => [
                'Investment'                => $this->fmt($investment),
                'Projected harvest income'  => $this->fmt($projectedHarvest),
            ],
            'tokens'          => $tokens,
            'headline_amount' => $investment,
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
