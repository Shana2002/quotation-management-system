<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Role model — admin / manager / executive.
 */
final class Role extends Model
{
    protected string $table = 'roles';

    protected array $fillable = ['name', 'description'];

    public function idByName(string $name): ?int
    {
        $row = $this->findBy('name', $name);
        return $row !== null ? (int) $row['id'] : null;
    }
}
