<?php

namespace App\Services;

class SearchBenchmarkService
{
    public function run(int $iterations = 5): array
    {
        $iterations = max(1, $iterations);

        $scenarios = [
            'baseline' => [
                'entity_types' => ['accounts', 'contacts', 'crm-entities'],
                'page' => 1,
                'per_page' => 15,
            ],
            'query_and_date' => [
                'entity_types' => ['accounts', 'contacts'],
                'query' => 'a',
                'created_from' => now()->subDays(30)->toDateString(),
                'created_to' => now()->toDateString(),
                'page' => 1,
                'per_page' => 15,
            ],
            'sorted_accounts' => [
                'entity_types' => ['accounts'],
                'sort_by' => 'name',
                'sort_direction' => 'asc',
                'page' => 1,
                'per_page' => 25,
            ],
        ];

        $results = [];

        foreach ($scenarios as $name => $filters) {
            $durations = [];

            for ($i = 0; $i < $iterations; $i++) {
                $startedAt = hrtime(true);
                app(SearchService::class)->search($filters);
                $durations[] = round((hrtime(true) - $startedAt) / 1_000_000, 2);
            }

            $results[$name] = [
                'filters' => $filters,
                'iterations' => $iterations,
                'durations_ms' => $durations,
                'avg_ms' => round(array_sum($durations) / count($durations), 2),
                'min_ms' => min($durations),
                'max_ms' => max($durations),
            ];
        }

        return $results;
    }
}
