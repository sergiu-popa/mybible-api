<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class MeTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    public function test_it_returns_the_authenticated_user(): void
    {
        $user = $this->givenAnAuthenticatedUser();

        $this->getJson(route('auth.me'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'created_at'],
            ])
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_it_returns_401_without_a_token(): void
    {
        $this->getJson(route('auth.me'))
            ->assertUnauthorized();
    }

    public function test_it_returns_401_with_an_expired_token(): void
    {
        config()->set('sanctum.expiration', 1);

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->travel(5)->minutes();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('auth.me'))
            ->assertUnauthorized();
    }
}
