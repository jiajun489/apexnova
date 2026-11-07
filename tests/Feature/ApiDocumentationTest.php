<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    public function test_openapi_spec_documents_bearer_auth_and_public_login(): void
    {
        // docs are local-only by default; open them for this test
        Gate::define('viewApiDocs', fn ($user = null) => true);

        $spec = $this->getJson('/docs/api.json')->assertOk()->json();

        $this->assertSame('bearer', $spec['components']['securitySchemes']['http']['scheme']);
        $this->assertSame([['http' => []]], $spec['security']);           // every endpoint needs a token...
        $this->assertSame([], $spec['paths']['/login']['post']['security']); // ...except login
        $this->assertArrayHasKey('/products', $spec['paths']);
        $this->assertArrayHasKey('/products/{product}', $spec['paths']);
    }

    public function test_docs_are_hidden_outside_local_without_permission(): void
    {
        $this->getJson('/docs/api.json')->assertForbidden();
    }
}
