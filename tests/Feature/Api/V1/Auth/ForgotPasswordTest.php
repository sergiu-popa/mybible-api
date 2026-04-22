<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Domain\Auth\Notifications\PasswordResetNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_a_password_reset_notification_to_a_known_user(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'jane@example.com',
        ]);

        $this->postJson(route('auth.forgot-password'), [
            'email' => 'jane@example.com',
        ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, PasswordResetNotification::class);
    }

    public function test_it_returns_200_without_sending_anything_for_an_unknown_email(): void
    {
        Notification::fake();

        $this->postJson(route('auth.forgot-password'), [
            'email' => 'ghost@example.com',
        ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertNothingSent();
    }

    public function test_it_returns_422_on_missing_email(): void
    {
        $this->postJson(route('auth.forgot-password'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_it_returns_422_on_malformed_email(): void
    {
        $this->postJson(route('auth.forgot-password'), [
            'email' => 'not-an-email',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
