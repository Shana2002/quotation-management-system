<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validator
 *
 * Minimal, dependency-free server-side validation. Rules are expressed as
 * pipe-delimited strings, e.g. 'required|email|max:190'.
 *
 * Supported rules:
 *   required, email, numeric, integer, min:n, max:n, in:a,b,c,
 *   date, confirmed, unique:table,column[,ignoreId]
 */
final class Validator
{
    /** @var array<string,string[]> field => list of error messages */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $data;

    /** @var array<string,string> */
    private array $rules;

    /** @var array<string,string> custom labels */
    private array $labels;

    /**
     * @param array<string,mixed>  $data
     * @param array<string,string> $rules
     * @param array<string,string> $labels
     */
    public function __construct(array $data, array $rules, array $labels = [])
    {
        $this->data   = $data;
        $this->rules  = $rules;
        $this->labels = $labels;
    }

    /**
     * Run validation and return true when there are no errors.
     */
    public function passes(): bool
    {
        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            foreach (explode('|', $ruleString) as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * @return array<string,string[]>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Flatten errors into a single message list.
     *
     * @return string[]
     */
    public function flatErrors(): array
    {
        $flat = [];
        foreach ($this->errors as $messages) {
            foreach ($messages as $message) {
                $flat[] = $message;
            }
        }

        return $flat;
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        if ($rule === '') {
            return;
        }

        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
        $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
        $str   = is_string($value) ? trim($value) : $value;

        switch ($name) {
            case 'required':
                if ($str === null || $str === '' || $str === []) {
                    $this->addError($field, "{$label} is required.");
                }
                break;

            case 'email':
                if (!empty($str) && !filter_var($str, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "{$label} must be a valid email address.");
                }
                break;

            case 'numeric':
                if (!empty($str) && !is_numeric($str)) {
                    $this->addError($field, "{$label} must be a number.");
                }
                break;

            case 'integer':
                if (!empty($str) && filter_var($str, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, "{$label} must be an integer.");
                }
                break;

            case 'min':
                if (!empty($str) && mb_strlen((string) $str) < (int) $param) {
                    $this->addError($field, "{$label} must be at least {$param} characters.");
                }
                break;

            case 'max':
                if (!empty($str) && mb_strlen((string) $str) > (int) $param) {
                    $this->addError($field, "{$label} must not exceed {$param} characters.");
                }
                break;

            case 'in':
                $options = explode(',', (string) $param);
                if (!empty($str) && !in_array((string) $str, $options, true)) {
                    $this->addError($field, "{$label} is invalid.");
                }
                break;

            case 'date':
                if (!empty($str) && strtotime((string) $str) === false) {
                    $this->addError($field, "{$label} must be a valid date.");
                }
                break;

            case 'confirmed':
                if (($this->data[$field . '_confirmation'] ?? null) !== $value) {
                    $this->addError($field, "{$label} confirmation does not match.");
                }
                break;

            case 'unique':
                $this->validateUnique($field, $label, (string) $str, (string) $param);
                break;
        }
    }

    /**
     * unique:table,column[,ignoreId]
     */
    private function validateUnique(string $field, string $label, string $value, string $param): void
    {
        if ($value === '') {
            return;
        }

        [$table, $column, $ignoreId] = array_pad(explode(',', $param), 3, null);
        if (!$table || !$column) {
            return;
        }

        // Identifiers are developer-supplied (not user input); still guard them.
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1 || preg_match('/^[A-Za-z0-9_]+$/', $column) !== 1) {
            return;
        }

        $db  = Database::connection();
        $sql = "SELECT COUNT(*) AS c FROM `{$table}` WHERE `{$column}` = :value";
        $params = ['value' => $value];

        if ($ignoreId !== null && $ignoreId !== '') {
            $sql .= " AND `id` <> :ignore";
            $params['ignore'] = (int) $ignoreId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        if ((int) ($stmt->fetch()['c'] ?? 0) > 0) {
            $this->addError($field, "{$label} is already in use.");
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
