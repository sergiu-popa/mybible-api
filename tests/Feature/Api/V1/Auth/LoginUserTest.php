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
        // The User model's `hashed` cast hashes `password` on create — the
        // plain value passed here is stored as a bcrypt hash.
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

    public function test_it_logs_in_a_user_with_a_symfony_argon2id_hash(): void
    {
        // Pre-baked Argon2id hash of the plaintext "symfony-original-pass",
        // generated with Symfony's production parameters (m=65536, t=4, p=1).
        // Proves that existing Symfony user rows verify under Laravel's
        // Argon2id hasher without any forced password reset at cutover.
        $legacyHash = '$argon2id$v=19$m=65536,t=4,p=1$LldGaTI1czRIRWI3OGhvdQ$1RQeVbmorryhz0LsIdoCuR1/0paYb25qvBVlR+PdTn8';

        $user = User::factory()->make([
            'email' => 'legacy@example.com',
        ]);
        // Bypass the `hashed` cast so the legacy hash is stored verbatim.
        $user->setRawAttributes(array_merge($user->getAttributes(), [
            'password' => $legacyHash,
        ]));
        $user->save();

        $this->postJson(route('auth.login'), [
            'email' => 'legacy@example.com',
            'password' => 'symfony-original-pass',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'legacy@example.com');
    }
}
