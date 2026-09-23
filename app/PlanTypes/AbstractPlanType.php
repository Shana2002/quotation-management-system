<?php

declare(strict_types=1);

namespace App\PlanTypes;

/**
 * AbstractPlanType
 *
 * Shared helpers for concrete plan types (currency formatting, numeric input
 * coercion). Concrete types implement the calculation-specific methods.
 */
abstract class AbstractPlanType implements PlanTypeInterface
{
    /** Format a numeric amount with the configured currency symbol. */
    protected function fmt(float $amount): string
    {
        $symbol = (string) config('currency_symbol', 'Rs.');
        return $symbol . ' ' . number_format($amount, 2);
    }

    /**
     * Format a payout figure rounded to the nearest whole rupee.
     *
     * Payouts are quoted in whole rupees, and a monthly one is a small
     * percentage of the capital, so it rarely lands on a whole rupee: 2% of
     * Rs. 1,041,666.50 is Rs. 20,833.33, which the letter shows as
     * Rs. 20,833.00 (14,666.66 goes the other way, to Rs. 14,667.00).
     *
     * Totals are deliberately NOT built from this rounded figure — they come
     * from the rate itself, so a year of monthly payouts still adds up to the
     * unrounded figure rather than to the rounded one × 12.
     */
    protected function fmtWhole(float $amount): string
    {
        return $this->fmt(round($amount));
    }

    /** Coerce a possibly-string input to float. */
    protected function num(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /** Coerce a possibly-string input to int. */
    protected function int(mixed $value): int
    {
        return (int) $value;
    }

    /**
     * Default no-op validation; concrete types override as needed.
     *
     * @param array<string,mixed> $inputs
     * @return string[]
     */
    public function validate(array $inputs): array
    {
        return [];
    }

    /** No summary template by default; types that ship a letter override this. */
    public function defaultSummary(): string
    {
        return '';
    }

    /** No terms template by default; types that ship a letter override this. */
    public function defaultTerms(): string
    {
        return '';
    }

    /**
     * Discover this type's `${token}` set by running a representative compute().
     *
     * Deriving the list from compute() itself (rather than hand-maintaining a
     * parallel array) guarantees the plan-edit reference panel can never list a
     * token compute() no longer emits, or miss one it started emitting.
     *
     * @param array<string,mixed> $params
     * @return array<string,string> token name => description
     */
    public function availableTokens(array $params): array
    {
        $inputs = [];
        foreach ($this->inputFields($params) as $field) {
            $inputs[$field['name']] = $this->sampleInput($field);
        }

        $tokens = $this->compute($inputs, $params)['tokens'] ?? [];
        $labels = $this->tokenLabels();

        $out = [];
        foreach (array_keys($tokens) as $name) {
            $out[$name] = $labels[$name] ?? ucfirst(str_replace('_', ' ', $name));
        }

        return $out;
    }

    /**
     * Human wording for this type's tokens, keyed by token name. Types override
     * to replace the generic "Term label" fallback with something clearer.
     *
     * @return array<string,string>
     */
    protected function tokenLabels(): array
    {
        return [];
    }

    /**
     * Assemble the letter's "Investment Plan Details" table from an ordered
     * label => value map. Rows whose value is an empty string are dropped, so a
     * type can list every row it might emit and let the data decide which
     * appear (e.g. the monthly-only rows on an annual payout).
     *
     * The table is deliberately flat rather than grouped into sections: the
     * reference letter the layout is modelled on is a plain two-column grid,
     * with rows like "Payment Plan" and "Investment Principal" being ordinary
     * label/value rows rather than section headings.
     *
     * @param array<string,string> $rows
     * @return array{title:string,headers:array<int,string>,rows:array<int,array{label:string,value:string}>}
     */
    protected function details(array $rows, string $title = 'Investment Plan Details'): array
    {
        $clean = [];
        foreach ($rows as $label => $value) {
            $value = (string) $value;
            if ($value === '') {
                continue;
            }
            $clean[] = ['label' => (string) $label, 'value' => $value];
        }

        return [
            'title'   => $title,
            'headers' => ['Description', 'Details'],
            'rows'    => $clean,
        ];
    }

    /** A representative input value, used only for token discovery. */
    protected function sampleInput(array $field): mixed
    {
        return match ($field['type'] ?? 'text') {
            'number' => 100000,
            'select' => (string) (array_key_first($field['options'] ?? []) ?? ''),
            default  => 'Sample',
        };
    }
}
