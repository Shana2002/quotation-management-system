<?php

declare(strict_types=1);

namespace App\PlanTypes;

/**
 * Monthly Wealth Plan — a one-time investment that the company repays in equal
 * monthly instalments over a fixed term (default 96 months / 8 years), at the
 * end of which the customer also receives a maturity benefit.
 *
 * Parameters:
 *   repay_months          : number of monthly repayments (default 96)
 *   monthly_repay_rate    : monthly repayment as % of the invested capital
 *   maturity_benefit_rate : end-of-term bonus as % of the invested capital
 */
final class MonthlyWealth extends AbstractPlanType
{
    public function key(): string
    {
        return 'monthly_wealth';
    }

    public function label(): string
    {
        return 'Monthly Wealth Plan';
    }

    public function letterTitle(): string
    {
        return 'Monthly Wealth Plan — Agarwood Investment';
    }

    public function formulaNote(): string
    {
        return 'Monthly re-payment = investment × monthly repay rate, paid for "repay months". '
            . 'Maturity benefit = investment × maturity benefit rate, paid at the end. '
            . 'Total value = total re-payments + maturity benefit.';
    }

    public function inputFields(array $params): array
    {
        return [
            ['name' => 'investment', 'label' => 'One-time Investment Amount', 'type' => 'number', 'step' => '0.01', 'required' => true],
        ];
    }

    public function defaultParameters(): array
    {
        // Placeholder values — adjust in Settings to match the real product.
        return [
            'repay_months'          => 96,
            'monthly_repay_rate'    => 1.5,
            'maturity_benefit_rate' => 25.0,
        ];
    }

    public function defaultBenefits(): string
    {
        return "• Single one-time investment, no recurring payments required.\n"
            . "• Steady monthly income for the full 8-year (96 month) term.\n"
            . "• Additional maturity benefit paid at the end of the term.\n"
            . "• Backed by an appreciating Agarwood plantation asset.";
    }

    public function validate(array $inputs): array
    {
        return $this->num($inputs['investment'] ?? 0) > 0
            ? []
            : ['Investment amount must be greater than zero.'];
    }

    public function compute(array $inputs, array $params): array
    {
        $investment = $this->num($inputs['investment'] ?? 0);
        $months     = max(1, $this->int($params['repay_months'] ?? 96));
        $monthlyRepay = $investment * ((float) ($params['monthly_repay_rate'] ?? 1.5) / 100);
        $totalRepaid  = $monthlyRepay * $months;
        $maturity     = $investment * ((float) ($params['maturity_benefit_rate'] ?? 25.0) / 100);
        $totalValue   = $totalRepaid + $maturity;

        return [
            'intro'   => 'Monthly Wealth plan with a one-time investment, repaid over '
                . $months . ' months (' . round($months / 12, 1) . ' years), is illustrated below.',
            'headers' => ['Investment', 'Monthly Re-payment', 'Term', 'Maturity Benefit', 'Total Value'],
            'rows'    => [[
                $this->fmt($investment),
                $this->fmt($monthlyRepay) . ' x ' . $months,
                round($months / 12, 1) . ' Years',
                $this->fmt($maturity),
                $this->fmt($totalValue),
            ]],
            'summary' => [
                'Investment'         => $this->fmt($investment),
                'Total re-payments'  => $this->fmt($totalRepaid),
                'Maturity benefit'   => $this->fmt($maturity),
                'Total value'        => $this->fmt($totalValue),
            ],
            'headline_amount' => $investment,
        ];
    }
}
