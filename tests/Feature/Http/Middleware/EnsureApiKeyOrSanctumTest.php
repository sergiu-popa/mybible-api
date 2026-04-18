<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class EnsureApiKeyOrSanctumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('api_keys.header', 'X-Api-Key');
        config()->set('api_keys.clients', [
            'mobile' => 'mobile-valid-key',
        ]);

        Route::middleware('api-key-or-sanctum')->get('/_test/combined', function (Request $request) {
            return response()->json([
                'user_id' => $request->user()?->id,
                'client' => $request->attributes->get('api_client'),
            ]);
        });
    }

    public function test_it_accepts_a_valid_bearer_token_and_resolves_the_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/_test/combined')
            ->assertOk()
            ->assertJson([
                'user_id' => $user->id,
                'client' => null,
            ]);
    }

    public function test_it_accepts_a_valid_api_key_without_user_context(): void
    {
        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson('/_test/combined')
            ->assertOk()
            ->assertJson([
                'user_id' => null,
                'client' => 'mobile',
            ]);
    }

    public function test_it_hard_fails_on_an_invalid_bearer_even_with_valid_api_key(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
            'X-Api-Key' => 'mobile-valid-key',
        ])
            ->getJson('/_test/combined')
            ->assertUnauthorized();
    }

    public function test_it_rejects_an_expired_bearer_token(): void
    {
        config()->set('sanctum.expiration', 1);

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->travel(5)->minutes();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/_test/combined')
            ->assertUnauthorized();
    }

    public function test_it_rejects_when_neither_credential_is_present(): void
    {
        $this->getJson('/_test/combined')->assertUnauthorized();
    }

    public function test_bearer_takes_precedence_over_api_key_header(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Api-Key' => 'mobile-valid-key',
        ])
            ->getJson('/_test/combined')
            ->assertOk()
            ->assertJson([
                'user_id' => $user->id,
                'client' => null,
            ]);
    }
}
