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
}
