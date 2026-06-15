<?php

declare(strict_types=1);

namespace App\PlanTypes;

/**
 * InterestPlanType
 *
 * Shared logic for the two interest/harvest-income products (Royal Plus and
 * Guaranteed Plus). The customer invests a capital sum for a chosen tenure and
 * receives harvest profit either monthly or annually; the capital plus all
 * profit is the "total maturity value".
 *
 * Concrete subclasses only differ by key/label and the allowed tenure years.
 *
 * Parameters shape (admin-editable JSON):
 *   ['years' => [ <year> => ['monthly_rate' => %perMonth, 'annual_rate' => %perYear], ... ]]
 * where the rates are a percentage of the invested capital.
 */
abstract class InterestPlanType extends AbstractPlanType
{
    /** Allowed tenures in years (e.g. [1,2,3,4]). */
    abstract protected function yearOptions(): array;

    public function letterTitle(): string
    {
        return 'Investing for Agarwood Land';
    }

    public function formulaNote(): string
    {
        return 'Monthly: profit = investment × monthly rate, paid ×12 each year. '
            . 'Annual: profit = investment × annual rate per year. '
            . 'Total maturity value = investment + (profit over the full tenure).';
    }

    public function inputFields(array $params): array
    {
        $years = [];
        foreach ($this->yearOptions() as $y) {
            $years[$y] = $y . ' Year' . ($y > 1 ? 's' : '');
        }

        return [
            ['name' => 'investment', 'label' => 'Investment Amount', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'period_years', 'label' => 'Tenure', 'type' => 'select', 'options' => $years, 'required' => true],
            ['name' => 'method', 'label' => 'Repayment Method', 'type' => 'select',
                'options' => ['monthly' => 'Monthly', 'annual' => 'Annual'], 'required' => true],
        ];
    }

    public function defaultParameters(): array
    {
        $years = [];
        foreach ($this->yearOptions() as $y) {
            // Seeded from the Royal Plus sample (1yr monthly = 2%/month = 24%/yr).
            // These are placeholders for other tenures — adjust in Settings.
            $years[$y] = ['monthly_rate' => 2.0, 'annual_rate' => 24.0];
        }

        return ['years' => $years];
    }

    public function validate(array $inputs): array
    {
        $errors = [];
        if ($this->num($inputs['investment'] ?? 0) <= 0) {
            $errors[] = 'Investment amount must be greater than zero.';
        }
        if (!in_array($this->int($inputs['period_years'] ?? 0), $this->yearOptions(), true)) {
            $errors[] = 'Please select a valid tenure.';
        }
        if (!in_array($inputs['method'] ?? '', ['monthly', 'annual'], true)) {
            $errors[] = 'Please select a repayment method.';
        }
        return $errors;
    }

    public function compute(array $inputs, array $params): array
    {
        $investment = $this->num($inputs['investment'] ?? 0);
        $year       = $this->int($inputs['period_years'] ?? ($this->yearOptions()[0] ?? 1));
        $method     = ($inputs['method'] ?? 'monthly') === 'annual' ? 'annual' : 'monthly';

        $rates = $params['years'][$year] ?? $params['years'][(string) $year] ?? ['monthly_rate' => 2.0, 'annual_rate' => 24.0];

        $yearLabel = $year . ' Year' . ($year > 1 ? 's' : '');

        if ($method === 'monthly') {
            $monthlyProfit = $investment * ((float) $rates['monthly_rate']/12 / 100);
            $totalProfit   = $monthlyProfit * 12 * $year;
            $maturity      = $investment + $totalProfit;

            $harvestCell = $this->fmt($monthlyProfit) . ' x 12' . ($year > 1 ? " x {$year}" : '');
            $headers = ['Year', 'Investment', 'Monthly harvest Profit', 'Total maturity value'];
        } else {
            $annualProfit = $investment * ((float) $rates['annual_rate'] / 100);
            $totalProfit  = $annualProfit * $year;
            $maturity     = $investment + $totalProfit;

            $harvestCell = $this->fmt($annualProfit) . ($year > 1 ? " x {$year}" : '');
            $headers = ['Year', 'Investment', 'Annual harvest Profit', 'Total maturity value'];
        }

        return [
            'intro'   => $this->label() . ' plan with harvest income will be made in the following manner.',
            'headers' => $headers,
            'rows'    => [[$yearLabel, $this->fmt($investment), $harvestCell, $this->fmt($maturity)]],
            'summary' => [
                'Investment'          => $this->fmt($investment),
                'Total harvest profit' => $this->fmt($totalProfit),
                'Total maturity value' => $this->fmt($maturity),
            ],
            'headline_amount' => $investment,
        ];
    }
}
