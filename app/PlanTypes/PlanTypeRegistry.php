<?php

declare(strict_types=1);

namespace App\PlanTypes;

/**
 * PlanTypeRegistry
 *
 * Central registry of the available plan types. Add new products here.
 */
final class PlanTypeRegistry
{
    /** @var array<string,PlanTypeInterface>|null */
    private static ?array $cache = null;

    /**
     * All plan types keyed by their machine key.
     *
     * @return array<string,PlanTypeInterface>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $types = [
            new RoyalPlus(),
            new GuaranteedPlus(),
            new MonthlyWealth(),
            new SupremePlus(),
            new GoldenCrop(),
            new PlantSelling(),
        ];

        $map = [];
        foreach ($types as $type) {
            $map[$type->key()] = $type;
        }

        return self::$cache = $map;
    }

    /**
     * Resolve a plan type by key, or null if unknown.
     */
    public static function get(string $key): ?PlanTypeInterface
    {
        return self::all()[$key] ?? null;
    }

    /**
     * key => label list, for dropdowns.
     *
     * @return array<string,string>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::all() as $key => $type) {
            $out[$key] = $type->label();
        }
        return $out;
    }
}
