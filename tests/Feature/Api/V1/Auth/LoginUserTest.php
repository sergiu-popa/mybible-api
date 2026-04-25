<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_it_logs_in_a_user_with_a_legacy_bcrypt_hash(): void
    {
        // Symfony's `auto` hasher fell back to bcrypt for some users (e.g. when
        // sodium wasn't available), so production rows can carry a `$2y$...`
        // hash. Verify those still authenticate after the cutover.
        $bcryptHash = Hash::driver('bcrypt')->make('legacy-bcrypt-pass');

        $user = User::factory()->make(['email' => 'bcrypt@example.com']);
        $user->setRawAttributes(array_merge($user->getAttributes(), [
            'password' => $bcryptHash,
        ]));
        $user->save();

        $this->postJson(route('auth.login'), [
            'email' => 'bcrypt@example.com',
            'password' => 'legacy-bcrypt-pass',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'bcrypt@example.com');
    }

    public function test_it_rehashes_a_legacy_bcrypt_password_to_argon2id_on_login(): void
    {
        $bcryptHash = Hash::driver('bcrypt')->make('legacy-bcrypt-pass');

        $user = User::factory()->make(['email' => 'bcrypt@example.com']);
        $user->setRawAttributes(array_merge($user->getAttributes(), [
            'password' => $bcryptHash,
        ]));
        $user->save();

        $this->postJson(route('auth.login'), [
            'email' => 'bcrypt@example.com',
            'password' => 'legacy-bcrypt-pass',
        ])->assertOk();

        $reloaded = User::find($user->id);
        $this->assertNotNull($reloaded);
        $stored = $reloaded->password;
        $this->assertNotSame($bcryptHash, $stored);
        $this->assertSame('argon2id', password_get_info($stored)['algoName']);
        $this->assertTrue(Hash::check('legacy-bcrypt-pass', $stored));
    }

    public function test_it_returns_401_for_an_unsupported_hash_algorithm(): void
    {
        // MD5/SHA1 etc. are not legitimate output from any era of this app.
        // Treat unknown algorithms as a credential failure rather than a 500.
        $user = User::factory()->make(['email' => 'broken@example.com']);
        $user->setRawAttributes(array_merge($user->getAttributes(), [
            'password' => md5('whatever'),
        ]));
        $user->save();

        $this->postJson(route('auth.login'), [
            'email' => 'broken@example.com',
            'password' => 'whatever',
        ])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Invalid credentials.']);
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
