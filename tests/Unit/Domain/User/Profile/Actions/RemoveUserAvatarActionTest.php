<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\User\Profile\Actions;

use App\Domain\User\Profile\Actions\RemoveUserAvatarAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class RemoveUserAvatarActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_the_file_and_clears_the_column(): void
    {
        $disk = Storage::fake('avatars');

        $path = UploadedFile::fake()->image('avatar.png')
            ->storeAs('42', 'existing.png', ['disk' => 'avatars']);

        $this->assertIsString($path);

        $user = User::factory()->create(['avatar' => $path]);

        $result = $this->app->make(RemoveUserAvatarAction::class)->execute($user);

        $this->assertNull($result->avatar);
        $disk->assertMissing($path);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'avatar' => null]);
    }

    public function test_it_is_noop_when_the_column_is_already_null(): void
    {
        Storage::fake('avatars');

        $user = User::factory()->create(['avatar' => null]);

        $result = $this->app->make(RemoveUserAvatarAction::class)->execute($user);

        $this->assertNull($result->avatar);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'avatar' => null]);
    }
}
