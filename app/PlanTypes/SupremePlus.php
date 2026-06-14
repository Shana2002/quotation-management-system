<?php

declare(strict_types=1);

namespace App\PlanTypes;

/**
 * Supreme Plus Plan — the customer pays a monthly contribution for a fixed
 * pay-in term (default 50 months) to complete the target capital. Once the
 * capital is complete the plan converts to a Monthly Wealth style product:
 * the company then repays the customer monthly and pays a maturity benefit.
 *
 * Parameters:
 *   contribution_months   : pay-in months to complete capital (default 50)
 *   repay_months          : post-conversion monthly repayments (default 96)
 *   monthly_repay_rate    : monthly repayment as % of completed capital
 *   maturity_benefit_rate : end-of-term bonus as % of completed capital
 */
final class SupremePlus extends AbstractPlanType
{
    public function key(): string
    {
        return 'supreme_plus';
    }

    public function label(): string
    {
        return 'Supreme Plus Plan';
    }

    public function letterTitle(): string
    {
        return 'Supreme Plus Plan — Agarwood Investment';
    }

    public function formulaNote(): string
    {
        return 'Completed capital = monthly payment × pay-in months. After conversion, '
            . 'monthly re-payment = capital × monthly repay rate (×repay months); '
            . 'maturity benefit = capital × maturity benefit rate.';
    }

    public function inputFields(array $params): array
    {
        return [
            ['name' => 'monthly_contribution', 'label' => 'Monthly Payment', 'type' => 'number', 'step' => '0.01', 'required' => true],
        ];
    }

    public function defaultParameters(): array
    {
        // Placeholder values — adjust in Settings to match the real product.
        return [
            'contribution_months'  => 50,
            'repay_months'         => 96,
            'monthly_repay_rate'   => 1.5,
            'maturity_benefit_rate' => 25.0,
        ];
    }

    public function defaultBenefits(): string
    {
        return "• Build capital with affordable monthly payments over the pay-in term.\n"
            . "• Automatically converts to a Monthly Wealth plan once complete.\n"
            . "• Earns monthly re-payments plus a maturity benefit thereafter.\n"
            . "• Ideal for disciplined, long-term wealth building.";
    }

    public function validate(array $inputs): array
    {
        return $this->num($inputs['monthly_contribution'] ?? 0) > 0
            ? []
            : ['Monthly payment must be greater than zero.'];
    }

    public function compute(array $inputs, array $params): array
    {
        $monthly       = $this->num($inputs['monthly_contribution'] ?? 0);
        $payInMonths   = max(1, $this->int($params['contribution_months'] ?? 50));
        $repayMonths   = max(1, $this->int($params['repay_months'] ?? 96));
        $completed     = $monthly * $payInMonths;
        $monthlyRepay  = $completed * ((float) ($params['monthly_repay_rate'] ?? 1.5) / 100);
        $totalRepaid   = $monthlyRepay * $repayMonths;
        $maturity      = $completed * ((float) ($params['maturity_benefit_rate'] ?? 25.0) / 100);
        $totalValue    = $totalRepaid + $maturity;

        return [
            'intro'   => 'Supreme Plus plan: complete your capital over ' . $payInMonths
                . ' monthly payments, after which it converts to a Monthly Wealth plan as shown below.',
            'headers' => ['Monthly Payment', 'Completed Capital', 'Monthly Re-payment', 'Maturity Benefit', 'Total Value'],
            'rows'    => [[
                $this->fmt($monthly) . ' x ' . $payInMonths,
                $this->fmt($completed),
                $this->fmt($monthlyRepay) . ' x ' . $repayMonths,
                $this->fmt($maturity),
                $this->fmt($totalValue),
            ]],
            'summary' => [
                'Completed capital' => $this->fmt($completed),
                'Total re-payments' => $this->fmt($totalRepaid),
                'Maturity benefit'  => $this->fmt($maturity),
                'Total value'       => $this->fmt($totalValue),
            ],
            'headline_amount' => $completed,
        ];
    }
}
