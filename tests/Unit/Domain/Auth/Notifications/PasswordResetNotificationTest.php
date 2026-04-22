<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Auth\Notifications;

use App\Domain\Auth\Notifications\PasswordResetNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

final class PasswordResetNotificationTest extends TestCase
{
    public function test_it_builds_the_reset_url_from_the_configured_base_and_includes_the_token_and_email(): void
    {
        config()->set('auth.password_reset_url', 'https://consumer.example.com/reset');

        $user = new User;
        $user->forceFill([
            'id' => 1,
            'email' => 'jane@example.com',
        ]);

        $notification = new PasswordResetNotification('the-raw-token');

        $this->assertSame(
            'https://consumer.example.com/reset?token=the-raw-token&email=' . rawurlencode('jane@example.com'),
            $notification->resetUrl($user),
        );
    }

    public function test_to_mail_includes_the_reset_url_and_raw_token(): void
    {
        config()->set('auth.password_reset_url', 'https://consumer.example.com/reset');
        config()->set('auth.defaults.passwords', 'users');
        config()->set('auth.passwords.users.expire', 60);

        $user = new User;
        $user->forceFill([
            'id' => 1,
            'email' => 'jane@example.com',
        ]);

        $mail = (new PasswordResetNotification('the-raw-token'))->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Reset your password', $mail->subject);
        $this->assertStringContainsString('the-raw-token', implode("\n", $mail->introLines));
        $this->assertStringStartsWith('https://consumer.example.com/reset?', $mail->actionUrl);
    }

    public function test_it_implements_should_queue(): void
    {
        $this->assertInstanceOf(
            ShouldQueue::class,
            new PasswordResetNotification('t'),
        );
    }
}
