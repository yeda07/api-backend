<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ApiIndex
{
    public static function paginateOrGet(Builder $query, array $filters = [], string $pageName = 'page')
    {
        if (!self::shouldPaginate($filters)) {
            return $query->get();
        }

        return $query->paginate(
            self::perPage($filters),
            ['*'],
            $pageName,
            self::page($filters)
        );
    }

    public static function shouldPaginate(array $filters = []): bool
    {
        if (array_key_exists('per_page', $filters) || array_key_exists('page', $filters)) {
            return true;
        }

        return (bool) config('performance.force_index_pagination', false);
    }

    public static function perPage(array $filters = []): int
    {
        $default = (int) config('performance.default_per_page', 25);
        $max = (int) config('performance.max_per_page', 100);
        $requested = (int) ($filters['per_page'] ?? $default);

        if ($requested <= 0) {
            $requested = $default;
        }

        return min($requested, $max);
    }

    public static function page(array $filters = []): int
    {
        $page = (int) ($filters['page'] ?? 1);

        return $page > 0 ? $page : 1;
    }

    public static function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ];
    }
}
