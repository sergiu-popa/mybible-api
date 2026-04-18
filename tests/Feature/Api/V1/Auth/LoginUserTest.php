<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LoginUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
        ]);

        $this->postJson(route('auth.login'), [
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'created_at'],
                    'token',
                ],
            ])
            ->assertJsonPath('data.user.email', 'jane@example.com');
    }

    public function test_it_returns_401_on_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
        ]);

        $this->postJson(route('auth.login'), [
            'email' => 'jane@example.com',
            'password' => 'wrong-pass',
        ])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_it_returns_401_on_unknown_email(): void
    {
        $this->postJson(route('auth.login'), [
            'email' => 'nobody@example.com',
            'password' => 'secret-pass',
        ])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_it_returns_422_on_missing_fields(): void
    {
        $this->postJson(route('auth.login'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
