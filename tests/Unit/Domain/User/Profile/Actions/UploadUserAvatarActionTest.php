<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\User\Profile\Actions;

use App\Domain\User\Profile\Actions\UploadUserAvatarAction;
use App\Domain\User\Profile\DataTransferObjects\UploadUserAvatarData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class UploadUserAvatarActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_the_file_and_updates_the_user_column(): void
    {
        $disk = Storage::fake('avatars');

        $user = User::factory()->create(['avatar' => null]);

        $result = $this->app->make(UploadUserAvatarAction::class)->execute(
            $user,
            new UploadUserAvatarData(UploadedFile::fake()->image('avatar.png')),
        );

        $this->assertNotNull($result->avatar);
        $this->assertStringStartsWith($user->id . '/', (string) $result->avatar);
        $disk->assertExists((string) $result->avatar);
    }

    public function test_it_removes_the_previous_avatar_when_replaced(): void
    {
        $disk = Storage::fake('avatars');

        $user = User::factory()->create(['avatar' => null]);

        $firstPath = $this->app->make(UploadUserAvatarAction::class)->execute(
            $user,
            new UploadUserAvatarData(UploadedFile::fake()->image('first.png')),
        )->avatar;

        $this->assertIsString($firstPath);
        $disk->assertExists($firstPath);

        $secondPath = $this->app->make(UploadUserAvatarAction::class)->execute(
            $user->refresh(),
            new UploadUserAvatarData(UploadedFile::fake()->image('second.png', 200, 200)),
        )->avatar;

        $this->assertIsString($secondPath);
        $this->assertNotSame($firstPath, $secondPath);
        $disk->assertMissing($firstPath);
        $disk->assertExists($secondPath);
    }
}
