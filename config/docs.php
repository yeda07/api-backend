<?php

return [
    'auth_enabled' => filter_var(env('DOCS_AUTH_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'username' => env('DOCS_USERNAME', ''),
    'password' => env('DOCS_PASSWORD', ''),
];
