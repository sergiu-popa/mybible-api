<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Profile;

use App\Domain\User\Profile\Events\UserAccountDeleted;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class DeleteUserAccountTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    public function test_happy_path_soft_deletes_the_user_and_revokes_all_tokens(): void
    {
        Event::fake([UserAccountDeleted::class]);

        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'Secret-pass1',
        ]);
        $user->createToken('another-device');

        $this->givenAnAuthenticatedUser($user);

        $this->deleteJson(route('profile.destroy'), [
            'password' => 'Secret-pass1',
        ])->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        Event::assertDispatched(UserAccountDeleted::class, function (UserAccountDeleted $event) use ($user): bool {
            return $event->userId === $user->id && $event->email === 'jane@example.com';
        });
    }

    public function test_it_returns_422_on_wrong_password(): void
    {
        $user = User::factory()->create([
            'password' => 'Secret-pass1',
        ]);
        $this->givenAnAuthenticatedUser($user);

        $this->deleteJson(route('profile.destroy'), [
            'password' => 'Wrong-pass1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    public function test_a_deleted_user_cannot_log_in_with_the_generic_401(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'Secret-pass1',
        ]);

        $this->givenAnAuthenticatedUser($user);
        $this->deleteJson(route('profile.destroy'), ['password' => 'Secret-pass1'])
            ->assertNoContent();

        $this->defaultHeaders = array_diff_key($this->defaultHeaders, ['Authorization' => '']);

        $this->postJson(route('auth.login'), [
            'email' => 'jane@example.com',
            'password' => 'Secret-pass1',
        ])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_re_registration_with_the_same_email_is_allowed(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'Secret-pass1',
        ]);

        $this->givenAnAuthenticatedUser($user);
        $this->deleteJson(route('profile.destroy'), ['password' => 'Secret-pass1'])
            ->assertNoContent();

        $this->defaultHeaders = array_diff_key($this->defaultHeaders, ['Authorization' => '']);

        $this->postJson(route('auth.register'), [
            'name' => 'New Jane',
            'email' => 'jane@example.com',
            'password' => 'Secret-pass1',
            'password_confirmation' => 'Secret-pass1',
        ])->assertCreated();
    }

    public function test_it_requires_authentication(): void
    {
        $this->deleteJson(route('profile.destroy'), ['password' => 'anything'])
            ->assertUnauthorized();
    }
}
