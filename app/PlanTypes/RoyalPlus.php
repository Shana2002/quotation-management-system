<?php

declare(strict_types=1);

namespace App\PlanTypes;

/**
 * Royal Plus — interest/harvest-income plan, tenures 1–4 years,
 * monthly or annual payout.
 */
final class RoyalPlus extends InterestPlanType
{
    public function key(): string
    {
        return 'royal_plus';
    }

    public function label(): string
    {
        return 'Royal Plus';
    }

    protected function yearOptions(): array
    {
        return [1, 2, 3, 4];
    }

    public function defaultBenefits(): string
    {
        return "• Guaranteed harvest income for the full tenure.\n"
            . "• Ownership share of an Agarwood plantation land.\n"
            . "• Full capital returned together with the total maturity value.\n"
            . "• Free to choose monthly or annual harvest payout.";
    }
}
