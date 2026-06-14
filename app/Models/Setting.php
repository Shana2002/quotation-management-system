<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Setting model — key/value store for company info, branding and defaults.
 */
final class Setting extends Model
{
    protected string $table = 'settings';

    protected array $fillable = ['setting_key', 'setting_value'];

    /** @var array<string,string>|null in-request cache */
    private static ?array $cache = null;

    /**
     * Return all settings as a key => value map (cached per request).
     *
     * @return array<string,string>
     */
    public function allAsMap(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $map = [];
        foreach ($this->all() as $row) {
            $map[$row['setting_key']] = (string) $row['setting_value'];
        }

        return self::$cache = $map;
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->allAsMap()[$key] ?? $default;
    }

    /**
     * Upsert a single setting (and invalidate the cache).
     */
    public function put(string $key, ?string $value): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO settings (setting_key, setting_value)
             VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $stmt->execute(['k' => $key, 'v' => $value]);
        self::$cache = null;
    }

    /**
     * Upsert many settings at once.
     *
     * @param array<string,?string> $pairs
     */
    public function putMany(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            $this->put($key, $value);
        }
    }
}
