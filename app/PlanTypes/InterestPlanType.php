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
 *   ['years' => [ <year> => ['annual_rate' => %perYear], ... ]]
 * where the rate is a percentage of the invested capital per YEAR. That single
 * figure is the source of truth: the monthly payout the letter quotes is
 * derived as annual_rate / 12 at compute time, so a plan can no longer hold a
 * monthly rate that disagrees with its annual one.
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
        return 'Each tenure carries one rate: the annual rate, as a percentage of the invested capital. '
            . 'Annual payout: profit = investment × annual rate. '
            . 'Monthly payout: profit = investment × (annual rate ÷ 12), paid ×12 each year. '
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
            // Seeded from the Royal Plus sample (24% a year = 2% a month).
            // These are placeholders for other tenures — adjust in Settings.
            $years[$y] = ['annual_rate' => 24.0];
        }

        return ['years' => $years];
    }

    public function defaultBenefits(): string
    {
        return "• Guaranteed harvest income for the full tenure.\n"
            . "• Ownership share of an Agarwood plantation land.\n"
            . "• Full capital returned together with the total maturity value.";
    }

    public function defaultSummary(): string
    {
        return "Based on the above quotation, the customer invests \${amount} under the Oxiaura \${plan_name} plan for a term of \${term_label}.\n"
            . "The agreed return is \${payout_amount}, payable \${payout_frequency}.\n"
            . "Total scheduled returns over the \${term_label} term: \${total_return}.";
    }

    public function defaultTerms(): string
    {
        return "The investment term is \${term_months} months from the agreed commencement date.\n"
            . "The return of \${payout_amount} shall be payable according to the agreed payment schedule.\n"
            . "The investment amount is \${amount}.\n"
            . "The investment agreement and applicable company terms shall govern the investment.\n"
            . "Any applicable taxes, statutory deductions, fees, or other charges shall be handled according to the applicable agreement and regulations.\n"
            . "This quotation is subject to formal acceptance and execution of the relevant investment agreement and required customer documentation.";
    }

    protected function tokenLabels(): array
    {
        return [
            'plan_name'        => 'Plan name, e.g. "Royal Plus"',
            'term_label'       => 'Tenure, e.g. "2 Years"',
            'term_years'       => 'Tenure in years, e.g. "2"',
            'term_months'      => 'Tenure in months, e.g. "24"',
            'amount'           => 'Investment amount with currency',
            'amount_number'    => 'Investment amount, digits only',
            'method'           => 'Repayment method key (monthly/annual)',
            'method_label'     => 'Repayment method, e.g. "Monthly Profit Payable"',
            'payout_label'     => 'Payout row label ("Monthly Return"/"Annual Return")',
            'payout_amount'    => 'Amount paid each period',
            'payout_frequency' => 'Payout wording, e.g. "monthly for 12 months"',
            'payments_count'   => 'Number of payments in the term',
            'monthly_return'   => 'Monthly return amount',
            'annual_return'    => 'One year of returns',
            'total_return'     => 'Total returns over the whole term',
            'total_maturity'   => 'Capital + total returns',
            'rate_monthly'     => 'Monthly rate as a percentage (annual rate ÷ 12)',
            'rate_annual'      => 'Annual rate as a percentage',
        ];
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

    /**
     * A tenure's annual rate, as a percentage of the invested capital.
     *
     * `annual_rate` is the stored truth. `monthly_rate` is honoured only as a
     * fallback for plan rows written before rates were stored annually — there
     * the monthly figure is the one that was entered, so it is ×12 to reach the
     * year. A row carrying both uses the annual figure: that is the point of
     * the single-rate shape, since the two could (and did) drift apart.
     *
     * @param array<string,mixed> $rates
     */
    private function annualRate(array $rates): float
    {
        if (is_numeric($rates['annual_rate'] ?? null)) {
            return (float) $rates['annual_rate'];
        }
        if (is_numeric($rates['monthly_rate'] ?? null)) {
            return (float) $rates['monthly_rate'] * 12;
        }
        return 24.0;
    }

    public function compute(array $inputs, array $params): array
    {
        $investment = $this->num($inputs['investment'] ?? 0);
        $year       = $this->int($inputs['period_years'] ?? ($this->yearOptions()[0] ?? 1));
        $method     = ($inputs['method'] ?? 'monthly') === 'annual' ? 'annual' : 'monthly';

        $rates = $params['years'][$year] ?? $params['years'][(string) $year] ?? [];

        $annualRate  = $this->annualRate($rates);
        $monthlyRate = $annualRate / 12;

        $yearLabel = $year . ' Year' . ($year > 1 ? 's' : '');
        $months    = $year * 12;

        // The annual rate is the stored truth, so every figure below comes from
        // it — including the monthly payout, which is the annual rate ÷ 12.
        // Deriving both from one number is what keeps a monthly quote and an
        // annual quote of the same plan consistent with each other.
        $annualProfit  = $investment * ($annualRate / 100);
        $monthlyProfit = $investment * ($monthlyRate / 100);
        $totalProfit   = $annualProfit * $year;
        $maturity      = $investment + $totalProfit;

        // The monthly figure is quoted in whole rupees; the annual and total
        // rows are not built from it (see AbstractPlanType::fmtWhole).
        $monthlyQuoted = $this->fmtWhole($monthlyProfit);

        $methodLabel  = $method === 'monthly' ? 'Monthly Profit Payable' : 'Annual Profit Payable';
        $payoutLabel  = $method === 'monthly' ? 'Monthly Return' : 'Annual Return';
        $payments     = $method === 'monthly' ? $months : $year;
        $frequency    = $method === 'monthly'
            ? 'monthly for ' . $months . ' months'
            : 'annually';

        $tokens = [
            'plan_name'        => $this->label(),
            'term_label'       => $yearLabel,
            'term_years'       => (string) $year,
            'term_months'      => (string) $months,
            'amount'           => $this->fmt($investment),
            'amount_number'    => number_format($investment, 2, '.', ''),
            'method'           => $method,
            'method_label'     => $methodLabel,
            'payout_label'     => $payoutLabel,
            'payout_amount'    => $method === 'monthly' ? $monthlyQuoted : $this->fmt($annualProfit),
            'payout_frequency' => $frequency,
            'payments_count'   => (string) $payments,
            'monthly_return'   => $monthlyQuoted,
            'annual_return'    => $this->fmt($annualProfit),
            'total_return'     => $this->fmt($totalProfit),
            'total_maturity'   => $this->fmt($maturity),
            'rate_monthly'     => number_format($monthlyRate, 2) . '%',
            'rate_annual'      => number_format($annualRate, 2) . '%',
        ];

        return [
            'intro'   => 'At the outset we thank you so much for giving us an opportunity to providing a price '
                . 'quotation for Oxiaura ' . $this->label() . ' Plan. Please pay your attention to following rates '
                . 'for the proposed service/ product of Oxiaura Plantation (Pvt) Ltd.',
            'details' => $this->details([
                'Investment Plan'            => $this->label(),
                'Investment Term'            => $yearLabel,
                'Total Investment'           => $this->fmt($investment),
                'Payment Plan'               => $methodLabel,
                'Monthly Return'             => $method === 'monthly' ? $monthlyQuoted : '',
                'Number of Monthly Payments' => $method === 'monthly' ? (string) $months : '',
                'Annual Return'              => $method === 'annual' ? $this->fmt($annualProfit) : '',
                // The year's profit straight off the annual rate — so it agrees
                // with the monthly row above × 12 only up to the rounding in it.
                'Total Annual Returns'       => $method === 'monthly' ? $this->fmt($annualProfit) : '',
                'Total Returns Over Term'    => $year > 1 ? $this->fmt($totalProfit) : '',
                'Investment Principal'       => $this->fmt($investment),
                'Total Maturity Value'       => $year > 1 ? $this->fmt($maturity) : '',
            ]),
            'headers' => [
                'Year',
                'Investment',
                $method === 'monthly' ? 'Monthly harvest Profit' : 'Annual harvest Profit',
                'Total maturity value',
            ],
            'rows' => [[
                $yearLabel,
                $this->fmt($investment),
                $method === 'monthly'
                    ? $monthlyQuoted . ' x ' . $months
                    : $this->fmt($annualProfit) . ($year > 1 ? " x {$year}" : ''),
                $this->fmt($maturity),
            ]],
            'summary' => [
                'Investment'           => $this->fmt($investment),
                'Total harvest profit' => $this->fmt($totalProfit),
                'Total maturity value' => $this->fmt($maturity),
            ],
            'tokens'          => $tokens,
            'headline_amount' => $investment,
        ];
    }
}
