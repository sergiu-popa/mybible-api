<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LogoutUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $firstToken = $user->createToken('first')->plainTextToken;
        $secondToken = $user->createToken('second');

        $this->withHeader('Authorization', 'Bearer ' . $firstToken)
            ->postJson(route('auth.logout'))
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $secondToken->accessToken->getKey(),
        ]);
    }

    public function test_it_returns_401_without_a_token(): void
    {
        $this->postJson(route('auth.logout'))
            ->assertUnauthorized();
    }
}
