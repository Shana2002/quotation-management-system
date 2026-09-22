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

    public function defaultSummary(): string
    {
        return "Based on the above quotation, the customer contributes \${monthly_contribution} monthly for \${contribution_months} months to complete a capital of \${amount} under the \${plan_name}.\n"
            . "After completion the plan converts to a monthly re-payment plan of \${monthly_repay}, payable for \${repay_months} months.\n"
            . "A maturity benefit of \${maturity_benefit} is paid at the end of the term, giving a total value of \${total_value}.";
    }

    public function defaultTerms(): string
    {
        return "The contribution term is \${contribution_months} months from the agreed commencement date.\n"
            . "The monthly payment of \${monthly_contribution} shall be payable according to the agreed payment schedule.\n"
            . "The completed capital is \${amount}.\n"
            . "Upon completion the plan converts to a \${repay_months}-month re-payment plan with a maturity benefit of \${maturity_benefit}.\n"
            . "Any applicable taxes, statutory deductions, fees, or other charges shall be handled according to the applicable agreement and regulations.\n"
            . "This quotation is subject to formal acceptance and execution of the relevant investment agreement and required customer documentation.";
    }

    protected function tokenLabels(): array
    {
        return [
            'plan_name'            => 'Plan name',
            'monthly_contribution' => 'Monthly payment during the pay-in term',
            'contribution_months'  => 'Number of pay-in months',
            'amount'               => 'Completed capital with currency',
            'amount_number'        => 'Completed capital, digits only',
            'term_label'           => 'Full term, e.g. "Pay-in 50m + Repay 96m"',
            'repay_months'         => 'Number of monthly re-payments',
            'monthly_repay'        => 'Monthly re-payment amount',
            'total_repaid'         => 'Sum of all re-payments',
            'maturity_benefit'     => 'Maturity benefit paid at the end',
            'total_value'          => 'Total value received',
            'rate_repay'           => 'Monthly re-payment rate as a percentage',
            'rate_maturity'        => 'Maturity benefit rate as a percentage',
        ];
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

        $termLabel = 'Pay-in ' . $payInMonths . ' months + Repay ' . $repayMonths . ' months';

        $tokens = [
            'plan_name'            => $this->label(),
            'monthly_contribution' => $this->fmt($monthly),
            'contribution_months'  => (string) $payInMonths,
            'amount'               => $this->fmt($completed),
            'amount_number'        => number_format($completed, 2, '.', ''),
            'term_label'           => $termLabel,
            'repay_months'         => (string) $repayMonths,
            'monthly_repay'        => $this->fmt($monthlyRepay),
            'total_repaid'         => $this->fmt($totalRepaid),
            'maturity_benefit'     => $this->fmt($maturity),
            'total_value'          => $this->fmt($totalValue),
            'rate_repay'           => number_format((float) ($params['monthly_repay_rate'] ?? 1.5), 2) . '%',
            'rate_maturity'        => number_format((float) ($params['maturity_benefit_rate'] ?? 25.0), 2) . '%',
        ];

        return [
            'intro'   => 'Supreme Plus plan: complete your capital over ' . $payInMonths
                . ' monthly payments, after which it converts to a Monthly Wealth plan as shown below.',
            'details' => $this->details([
                [
                    'title' => 'Contribution Plan',
                    'rows'  => [
                        'Investment Plan'          => $this->label(),
                        'Monthly Payment'          => $this->fmt($monthly),
                        'Number of Monthly Payments' => (string) $payInMonths,
                        'Completed Capital'        => $this->fmt($completed),
                    ],
                ],
                [
                    'title' => 'Payment Plan',
                    'rows'  => [
                        'Monthly Re-payment'         => $this->fmt($monthlyRepay),
                        'Number of Monthly Payments' => (string) $repayMonths,
                    ],
                ],
                [
                    'title' => 'Returns',
                    'rows'  => [
                        'Total Re-payments' => $this->fmt($totalRepaid),
                        'Maturity Benefit'  => $this->fmt($maturity),
                        'Total Value'       => $this->fmt($totalValue),
                    ],
                ],
            ]),
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
            'tokens'          => $tokens,
            'headline_amount' => $completed,
        ];
    }
}
