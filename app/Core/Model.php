<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Model
 *
 * Base data-access class. Provides generic, reusable CRUD helpers built
 * exclusively on PDO prepared statements (no string-concatenated values),
 * so every subclass is safe against SQL injection by construction.
 *
 * Subclasses set $table and (optionally) $fillable.
 */
abstract class Model
{
    /** Database table name. */
    protected string $table = '';

    /** Primary key column. */
    protected string $primaryKey = 'id';

    /** Columns that may be mass-assigned via create()/update(). */
    protected array $fillable = [];

    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Find a single row by primary key.
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Find a single row by an arbitrary column.
     */
    public function findBy(string $column, mixed $value): ?array
    {
        $column = $this->guardColumn($column);
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$column}` = :value LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['value' => $value]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Return all rows, optionally ordered.
     */
    public function all(string $orderBy = null, string $direction = 'ASC'): array
    {
        $sql = "SELECT * FROM `{$this->table}`";
        if ($orderBy !== null) {
            $orderBy   = $this->guardColumn($orderBy);
            $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY `{$orderBy}` {$direction}";
        }

        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Return rows matching simple equality conditions.
     *
     * @param array<string,mixed> $conditions column => value
     */
    public function where(array $conditions, string $orderBy = null, string $direction = 'ASC'): array
    {
        [$clause, $params] = $this->buildWhere($conditions);

        $sql = "SELECT * FROM `{$this->table}`";
        if ($clause !== '') {
            $sql .= " WHERE {$clause}";
        }
        if ($orderBy !== null) {
            $orderBy   = $this->guardColumn($orderBy);
            $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY `{$orderBy}` {$direction}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Count rows, optionally filtered by simple equality conditions.
     *
     * @param array<string,mixed> $conditions
     */
    public function count(array $conditions = []): int
    {
        [$clause, $params] = $this->buildWhere($conditions);
        $sql = "SELECT COUNT(*) AS c FROM `{$this->table}`";
        if ($clause !== '') {
            $sql .= " WHERE {$clause}";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    /**
     * Insert a new row from an associative array of column => value.
     * Only $fillable keys are honoured. Returns the new row id.
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $data = $this->onlyFillable($data);
        $columns = array_keys($data);

        $cols         = implode(', ', array_map(fn ($c) => "`{$c}`", $columns));
        $placeholders = implode(', ', array_map(fn ($c) => ":{$c}", $columns));

        $sql = "INSERT INTO `{$this->table}` ({$cols}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a row by primary key. Only $fillable keys are honoured.
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $data = $this->onlyFillable($data);
        if ($data === []) {
            return false;
        }

        $assignments = implode(', ', array_map(fn ($c) => "`{$c}` = :{$c}", array_keys($data)));
        $sql = "UPDATE `{$this->table}` SET {$assignments} WHERE `{$this->primaryKey}` = :__pk";

        $data['__pk'] = $id;
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($data);
    }

    /**
     * Delete a row by primary key.
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Expose the underlying PDO connection for bespoke queries in subclasses.
     */
    protected function connection(): PDO
    {
        return $this->db;
    }

    /**
     * Keep only fillable keys from $data.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function onlyFillable(array $data): array
    {
        if ($this->fillable === []) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Build a parameterised WHERE clause from equality conditions.
     *
     * @param array<string,mixed> $conditions
     * @return array{0:string,1:array<string,mixed>}
     */
    protected function buildWhere(array $conditions): array
    {
        if ($conditions === []) {
            return ['', []];
        }

        $parts  = [];
        $params = [];
        foreach ($conditions as $column => $value) {
            $column = $this->guardColumn($column);
            $parts[]            = "`{$column}` = :{$column}";
            $params[$column]    = $value;
        }

        return [implode(' AND ', $parts), $params];
    }

    /**
     * Allow only safe identifier characters in column names that are
     * interpolated into SQL (defence in depth against injection).
     */
    protected function guardColumn(string $column): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $column) !== 1) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }

        return $column;
    }
}
