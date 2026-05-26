<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_openapi_yaml_is_served_when_docs_auth_is_disabled(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        config(['docs.auth_enabled' => false]);
        putenv('DOCS_AUTH_ENABLED=false');

        $this->get('/openapi.yaml')
            ->assertOk()
            ->assertHeader('content-type', 'application/yaml; charset=UTF-8');

        $this->assertStringContainsString('openapi: 3.0.3', file_get_contents(public_path('openapi.yaml')));
    }
}
