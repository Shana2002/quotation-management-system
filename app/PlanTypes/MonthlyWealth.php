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

    public function defaultSummary(): string
    {
        return "Based on the above quotation, the customer invests \${amount} under the \${plan_name} for a term of \${term_label}.\n"
            . "The agreed monthly re-payment is \${monthly_repay}, payable for \${payments_count} months.\n"
            . "A maturity benefit of \${maturity_benefit} is paid at the end of the term, giving a total value of \${total_value}.";
    }

    public function defaultTerms(): string
    {
        return "The investment term is \${term_months} months from the agreed commencement date.\n"
            . "The monthly re-payment of \${monthly_repay} shall be payable according to the agreed payment schedule.\n"
            . "A maturity benefit of \${maturity_benefit} shall be paid at the end of the term.\n"
            . "The investment amount is \${amount}.\n"
            . "Any applicable taxes, statutory deductions, fees, or other charges shall be handled according to the applicable agreement and regulations.\n"
            . "This quotation is subject to formal acceptance and execution of the relevant investment agreement and required customer documentation.";
    }

    protected function tokenLabels(): array
    {
        return [
            'plan_name'        => 'Plan name',
            'amount'           => 'Investment amount with currency',
            'amount_number'    => 'Investment amount, digits only',
            'term_months'      => 'Term in months, e.g. "96"',
            'term_years'       => 'Term in years, e.g. "8"',
            'term_label'       => 'Term, e.g. "8 Years"',
            'monthly_repay'    => 'Monthly re-payment amount',
            'payments_count'   => 'Number of monthly re-payments',
            'total_repaid'     => 'Sum of all re-payments',
            'maturity_benefit' => 'Maturity benefit paid at the end',
            'total_value'      => 'Total value received',
            'rate_repay'       => 'Monthly re-payment rate as a percentage',
            'rate_maturity'    => 'Maturity benefit rate as a percentage',
        ];
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

        $termYears = round($months / 12, 1);
        $termLabel = $termYears . ' Years (' . $months . ' months)';

        $tokens = [
            'plan_name'        => $this->label(),
            'amount'           => $this->fmt($investment),
            'amount_number'    => number_format($investment, 2, '.', ''),
            'term_months'      => (string) $months,
            'term_years'       => (string) $termYears,
            'term_label'       => $termLabel,
            'monthly_repay'    => $this->fmt($monthlyRepay),
            'payments_count'   => (string) $months,
            'total_repaid'     => $this->fmt($totalRepaid),
            'maturity_benefit' => $this->fmt($maturity),
            'total_value'      => $this->fmt($totalValue),
            'rate_repay'       => number_format((float) ($params['monthly_repay_rate'] ?? 1.5), 2) . '%',
            'rate_maturity'    => number_format((float) ($params['maturity_benefit_rate'] ?? 25.0), 2) . '%',
        ];

        return [
            'intro'   => 'Monthly Wealth plan with a one-time investment, repaid over '
                . $months . ' months (' . $termYears . ' years), is illustrated below.',
            'details' => $this->details([
                [
                    'title' => 'Investment Plan',
                    'rows'  => [
                        'Investment Plan' => $this->label(),
                        'Investment'      => $this->fmt($investment),
                        'Investment Term' => $termLabel,
                    ],
                ],
                [
                    'title' => 'Payment Plan',
                    'rows'  => [
                        'Monthly Re-payment'         => $this->fmt($monthlyRepay),
                        'Number of Monthly Payments' => (string) $months,
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
            'headers' => ['Investment', 'Monthly Re-payment', 'Term', 'Maturity Benefit', 'Total Value'],
            'rows'    => [[
                $this->fmt($investment),
                $this->fmt($monthlyRepay) . ' x ' . $months,
                $termYears . ' Years',
                $this->fmt($maturity),
                $this->fmt($totalValue),
            ]],
            'summary' => [
                'Investment'         => $this->fmt($investment),
                'Total re-payments'  => $this->fmt($totalRepaid),
                'Maturity benefit'   => $this->fmt($maturity),
                'Total value'        => $this->fmt($totalValue),
            ],
            'tokens'          => $tokens,
            'headline_amount' => $investment,
        ];
    }
}
