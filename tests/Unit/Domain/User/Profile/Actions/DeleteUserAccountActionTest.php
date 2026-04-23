<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\User\Profile\Actions;

use App\Domain\User\Profile\Actions\DeleteUserAccountAction;
use App\Domain\User\Profile\DataTransferObjects\DeleteUserAccountData;
use App\Domain\User\Profile\Events\UserAccountDeleted;
use App\Domain\User\Profile\Exceptions\IncorrectCurrentPasswordException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class DeleteUserAccountActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_soft_deletes_revokes_tokens_and_dispatches_event(): void
    {
        Event::fake([UserAccountDeleted::class]);

        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'Secret-pass1',
        ]);

        $user->createToken('first');
        $user->createToken('second');

        $this->app->make(DeleteUserAccountAction::class)->execute(
            $user,
            new DeleteUserAccountData(password: 'Secret-pass1'),
        );

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        Event::assertDispatched(UserAccountDeleted::class, function (UserAccountDeleted $event) use ($user): bool {
            return $event->userId === $user->id && $event->email === 'jane@example.com';
        });
    }

    public function test_it_throws_on_wrong_password_and_leaves_state_untouched(): void
    {
        Event::fake([UserAccountDeleted::class]);

        $user = User::factory()->create([
            'password' => 'Secret-pass1',
        ]);
        $user->createToken('kept');

        try {
            $this->app->make(DeleteUserAccountAction::class)->execute(
                $user,
                new DeleteUserAccountData(password: 'Wrong-pass1'),
            );

            $this->fail('Expected IncorrectCurrentPasswordException.');
        } catch (IncorrectCurrentPasswordException $e) {
            $this->assertArrayHasKey('password', $e->errors());
        }

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        Event::assertNotDispatched(UserAccountDeleted::class);
    }
}
