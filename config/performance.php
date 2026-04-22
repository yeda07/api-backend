<?php

return [
    'force_index_pagination' => env('API_FORCE_INDEX_PAGINATION', false),
    'default_per_page' => (int) env('API_DEFAULT_PER_PAGE', 25),
    'max_per_page' => (int) env('API_MAX_PER_PAGE', 100),
    'rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 240),
    'guest_rate_limit_per_minute' => (int) env('API_GUEST_RATE_LIMIT_PER_MINUTE', 60),
];
