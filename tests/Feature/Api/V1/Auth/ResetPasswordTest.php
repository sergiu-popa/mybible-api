<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_happy_path_updates_the_hash_and_lets_the_user_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
        ]);

        $token = Password::broker()->createToken($user);

        $this->postJson(route('auth.reset-password'), [
            'email' => 'jane@example.com',
            'token' => $token,
            'password' => 'Brand-new-pass1',
            'password_confirmation' => 'Brand-new-pass1',
        ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $user->refresh();
        $this->assertTrue(Hash::check('Brand-new-pass1', $user->password));

        $this->postJson(route('auth.login'), [
            'email' => 'jane@example.com',
            'password' => 'Brand-new-pass1',
        ])->assertOk();
    }

    public function test_it_returns_422_on_tampered_token(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
        ]);

        Password::broker()->createToken($user);

        $this->postJson(route('auth.reset-password'), [
            'email' => 'jane@example.com',
            'token' => 'this-is-not-the-real-token',
            'password' => 'Brand-new-pass1',
            'password_confirmation' => 'Brand-new-pass1',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_it_returns_422_on_expired_token(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
        ]);

        $token = Password::broker()->createToken($user);

        $expireMinutes = (int) config('auth.passwords.users.expire');
        Carbon::setTestNow(Carbon::now()->addMinutes($expireMinutes + 1));

        $this->postJson(route('auth.reset-password'), [
            'email' => 'jane@example.com',
            'token' => $token,
            'password' => 'Brand-new-pass1',
            'password_confirmation' => 'Brand-new-pass1',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_it_returns_422_on_short_password(): void
    {
        $this->postJson(route('auth.reset-password'), [
            'email' => 'jane@example.com',
            'token' => 'anything',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_it_returns_422_on_password_confirmation_mismatch(): void
    {
        $this->postJson(route('auth.reset-password'), [
            'email' => 'jane@example.com',
            'token' => 'anything',
            'password' => 'Brand-new-pass1',
            'password_confirmation' => 'Something-else1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_it_returns_422_on_missing_fields(): void
    {
        $this->postJson(route('auth.reset-password'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'token', 'password']);
    }
}
