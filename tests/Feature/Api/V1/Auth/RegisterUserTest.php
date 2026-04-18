<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RegisterUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_user_and_returns_a_token(): void
    {
        $response = $this->postJson(route('auth.register'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'created_at'],
                    'token',
                ],
            ])
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonPath('data.user.name', 'Jane Doe');

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_the_issued_token_authenticates_me(): void
    {
        $token = $this->postJson(route('auth.register'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->json('data.token');

        $this->assertIsString($token);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('auth.me'))
            ->assertOk()
            ->assertJsonPath('data.email', 'jane@example.com');
    }

    public function test_it_returns_422_on_missing_fields(): void
    {
        $this->postJson(route('auth.register'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_it_returns_422_on_duplicate_email(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson(route('auth.register'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_it_returns_422_when_password_confirmation_mismatches(): void
    {
        $this->postJson(route('auth.register'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'other-pass',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }
}
