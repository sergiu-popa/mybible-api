<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\User\Profile\Actions;

use App\Domain\User\Profile\Actions\ChangeUserPasswordAction;
use App\Domain\User\Profile\DataTransferObjects\ChangeUserPasswordData;
use App\Domain\User\Profile\Exceptions\IncorrectCurrentPasswordException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

final class ChangeUserPasswordActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_changes_the_password_and_revokes_other_tokens(): void
    {
        $user = User::factory()->create([
            'password' => 'Old-password1',
        ]);

        $currentToken = $user->createToken('current')->accessToken;
        $otherToken = $user->createToken('other')->accessToken;
        $this->assertInstanceOf(PersonalAccessToken::class, $currentToken);
        $this->assertInstanceOf(PersonalAccessToken::class, $otherToken);

        $this->app->make(ChangeUserPasswordAction::class)->execute(
            $user,
            new ChangeUserPasswordData(
                currentPassword: 'Old-password1',
                newPassword: 'New-password2',
            ),
            $currentToken,
        );

        $user->refresh();
        $this->assertTrue(Hash::check('New-password2', $user->password));

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $currentToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherToken->id]);
    }

    public function test_it_throws_on_wrong_current_password_without_any_state_change(): void
    {
        $user = User::factory()->create([
            'password' => 'Old-password1',
        ]);

        $currentToken = $user->createToken('current')->accessToken;
        $otherToken = $user->createToken('other')->accessToken;
        $this->assertInstanceOf(PersonalAccessToken::class, $currentToken);
        $this->assertInstanceOf(PersonalAccessToken::class, $otherToken);

        try {
            $this->app->make(ChangeUserPasswordAction::class)->execute(
                $user,
                new ChangeUserPasswordData(
                    currentPassword: 'Wrong-password1',
                    newPassword: 'New-password2',
                ),
                $currentToken,
            );

            $this->fail('Expected IncorrectCurrentPasswordException.');
        } catch (IncorrectCurrentPasswordException $e) {
            $this->assertArrayHasKey('current_password', $e->errors());
        }

        $user->refresh();
        $this->assertTrue(Hash::check('Old-password1', $user->password));

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $currentToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->id]);
    }
}
