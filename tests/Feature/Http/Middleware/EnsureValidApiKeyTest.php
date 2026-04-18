<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class EnsureValidApiKeyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('api_keys.header', 'X-Api-Key');
        config()->set('api_keys.clients', [
            'mobile' => 'mobile-valid-key',
            'admin' => 'admin-valid-key',
        ]);

        Route::middleware('api-key')->get('/_test/api-key', function (Request $request) {
            return response()->json([
                'client' => $request->attributes->get('api_client'),
            ]);
        });
    }

    public function test_it_passes_with_a_valid_key(): void
    {
        $this->withHeader('X-Api-Key', 'admin-valid-key')
            ->getJson('/_test/api-key')
            ->assertOk()
            ->assertJson(['client' => 'admin']);
    }

    public function test_it_rejects_missing_header(): void
    {
        $this->getJson('/_test/api-key')
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_it_rejects_an_unknown_key(): void
    {
        $this->withHeader('X-Api-Key', 'not-a-valid-key')
            ->getJson('/_test/api-key')
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_it_attaches_the_matched_client_name(): void
    {
        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson('/_test/api-key')
            ->assertOk()
            ->assertJsonPath('client', 'mobile');
    }
}
