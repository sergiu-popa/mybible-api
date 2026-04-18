<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Auth\Actions;

use App\Domain\Auth\Actions\LogoutCurrentTokenAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

final class LogoutCurrentTokenActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_only_the_current_token(): void
    {
        $user = User::factory()->create();

        $firstToken = $user->createToken('first');
        $secondToken = $user->createToken('second');

        /** @var PersonalAccessToken $currentAccessToken */
        $currentAccessToken = PersonalAccessToken::findOrFail($firstToken->accessToken->getKey());
        $user->withAccessToken($currentAccessToken);

        $action = $this->app->make(LogoutCurrentTokenAction::class);
        $action->execute($user);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $firstToken->accessToken->getKey(),
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $secondToken->accessToken->getKey(),
        ]);
    }

    public function test_it_returns_silently_when_there_is_no_current_token(): void
    {
        $user = User::factory()->create();
        $user->createToken('some-token');

        $action = $this->app->make(LogoutCurrentTokenAction::class);
        $action->execute($user);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
