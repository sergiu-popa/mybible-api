<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Auth\Actions;

use App\Domain\Auth\Actions\LoginUserAction;
use App\Domain\Auth\DataTransferObjects\LoginUserData;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LoginUserActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_an_auth_token_on_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
        ]);

        $action = $this->app->make(LoginUserAction::class);

        $result = $action->execute(new LoginUserData(
            email: 'jane@example.com',
            password: 'secret-pass',
        ));

        $this->assertSame($user->id, $result->user->id);
        $this->assertNotEmpty($result->plainTextToken);
    }

    public function test_it_throws_on_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
        ]);

        $action = $this->app->make(LoginUserAction::class);

        $this->expectException(InvalidCredentialsException::class);

        $action->execute(new LoginUserData(
            email: 'jane@example.com',
            password: 'wrong-pass',
        ));
    }

    public function test_it_throws_on_unknown_email(): void
    {
        $action = $this->app->make(LoginUserAction::class);

        $this->expectException(InvalidCredentialsException::class);

        $action->execute(new LoginUserData(
            email: 'nobody@example.com',
            password: 'secret-pass',
        ));
    }
}
