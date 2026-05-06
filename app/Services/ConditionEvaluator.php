<?php

namespace App\Services;

class ConditionEvaluator
{
    public function matches(array $conditions, array|object $payload, string $logic = 'AND'): bool
    {
        if ($conditions === []) {
            return true;
        }

        $results = collect($conditions)->map(fn (array $condition) => $this->matchesOne($condition, $payload));

        return strtoupper($logic) === 'OR'
            ? $results->contains(true)
            : !$results->contains(false);
    }

    private function matchesOne(array $condition, array|object $payload): bool
    {
        $actual = data_get($payload, $condition['field'] ?? '');
        $expected = $condition['value'] ?? null;

        return match ($condition['operator'] ?? null) {
            'equals' => $actual == $expected,
            'not_equals' => $actual != $expected,
            'contains' => str_contains((string) $actual, (string) $expected),
            'greater_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'less_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'in' => in_array($actual, (array) $expected, true),
            'not_in' => !in_array($actual, (array) $expected, true),
            default => false,
        };
    }
}
