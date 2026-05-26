<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant schema mode
    |--------------------------------------------------------------------------
    |
    | shared: comportamiento actual, todas las tablas viven en public con tenant_id.
    | schema: modo futuro, cada tenant opera sobre su propio schema PostgreSQL.
    |
    */
    'mode' => env('TENANCY_MODE', 'shared'),

    'schema_prefix' => env('TENANT_SCHEMA_PREFIX', 'tenant'),
];
