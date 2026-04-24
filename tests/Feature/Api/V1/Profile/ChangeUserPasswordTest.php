<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class ChangeUserPasswordTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    public function test_happy_path_changes_hash_and_revokes_other_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'Old-password1',
        ]);

        $otherToken = $user->createToken('another-device');
        $this->givenAnAuthenticatedUser($user);

        $this->postJson(route('profile.change-password'), [
            'current_password' => 'Old-password1',
            'new_password' => 'New-password1',
            'new_password_confirmation' => 'New-password1',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'jane@example.com');

        $user->refresh();
        $this->assertTrue(Hash::check('New-password1', $user->password));

        // The other-device token is revoked, but the current-device token
        // still authenticates `me`.
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherToken->accessToken->id]);

        $this->getJson(route('auth.me'))->assertOk();

        // Login with the new password succeeds.
        $this->defaultHeaders = array_diff_key($this->defaultHeaders, ['Authorization' => '']);
        $this->postJson(route('auth.login'), [
            'email' => 'jane@example.com',
            'password' => 'New-password1',
        ])->assertOk();
    }

    public function test_it_returns_422_on_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'Old-password1',
        ]);
        $this->givenAnAuthenticatedUser($user);

        $this->postJson(route('profile.change-password'), [
            'current_password' => 'Wrong-password1',
            'new_password' => 'New-password1',
            'new_password_confirmation' => 'New-password1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $user->refresh();
        $this->assertTrue(Hash::check('Old-password1', $user->password));
    }

    public function test_it_returns_422_on_weak_new_password(): void
    {
        $user = User::factory()->create(['password' => 'Old-password1']);
        $this->givenAnAuthenticatedUser($user);

        $this->postJson(route('profile.change-password'), [
            'current_password' => 'Old-password1',
            'new_password' => 'short',
            'new_password_confirmation' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_it_returns_422_when_new_password_equals_current(): void
    {
        $user = User::factory()->create(['password' => 'Same-password1']);
        $this->givenAnAuthenticatedUser($user);

        $this->postJson(route('profile.change-password'), [
            'current_password' => 'Same-password1',
            'new_password' => 'Same-password1',
            'new_password_confirmation' => 'Same-password1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_it_requires_authentication(): void
    {
        $this->postJson(route('profile.change-password'), [
            'current_password' => 'x',
            'new_password' => 'New-password1',
            'new_password_confirmation' => 'New-password1',
        ])->assertUnauthorized();
    }
}
