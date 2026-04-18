<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Auth\Actions;

use App\Domain\Auth\Actions\RegisterUserAction;
use App\Domain\Auth\DataTransferObjects\RegisterUserData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class RegisterUserActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_user_and_returns_an_auth_token(): void
    {
        $action = $this->app->make(RegisterUserAction::class);

        $result = $action->execute(new RegisterUserData(
            name: 'Jane Doe',
            email: 'jane@example.com',
            password: 'secret-pass',
        ));

        $this->assertInstanceOf(User::class, $result->user);
        $this->assertSame('Jane Doe', $result->user->name);
        $this->assertSame('jane@example.com', $result->user->email);
        $this->assertNotEmpty($result->plainTextToken);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'name' => 'Jane Doe',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_it_hashes_the_password(): void
    {
        $action = $this->app->make(RegisterUserAction::class);

        $result = $action->execute(new RegisterUserData(
            name: 'Jane Doe',
            email: 'jane@example.com',
            password: 'secret-pass',
        ));

        $this->assertNotSame('secret-pass', $result->user->password);
        $this->assertTrue(Hash::check('secret-pass', $result->user->password));
    }
}
