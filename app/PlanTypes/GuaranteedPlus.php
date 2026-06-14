<?php

declare(strict_types=1);

namespace App\PlanTypes;

/**
 * Guaranteed Plus — interest/harvest-income plan like Royal Plus but with
 * tenures of 2–5 years, monthly or annual payout.
 */
final class GuaranteedPlus extends InterestPlanType
{
    public function key(): string
    {
        return 'guaranteed_plus';
    }

    public function label(): string
    {
        return 'Guaranteed Plus';
    }

    protected function yearOptions(): array
    {
        return [2, 3, 4, 5];
    }

    public function defaultBenefits(): string
    {
        return "• Guaranteed harvest income across a 2–5 year tenure.\n"
            . "• Higher returns for longer commitment periods.\n"
            . "• Full capital returned together with the total maturity value.\n"
            . "• Free to choose monthly or annual harvest payout.";
    }
}
