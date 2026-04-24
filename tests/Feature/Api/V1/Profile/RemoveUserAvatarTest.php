<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class RemoveUserAvatarTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    public function test_it_removes_the_file_and_clears_the_column(): void
    {
        $disk = Storage::fake('avatars');

        $path = UploadedFile::fake()->image('avatar.png')
            ->storeAs('42', 'existing.png', ['disk' => 'avatars']);
        $this->assertIsString($path);

        $user = User::factory()->create(['avatar' => $path]);
        $this->givenAnAuthenticatedUser($user);

        $this->deleteJson(route('profile.avatar.destroy'))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.avatar_url', null);

        $disk->assertMissing($path);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'avatar' => null]);
    }

    public function test_it_is_idempotent_when_no_avatar_is_set(): void
    {
        Storage::fake('avatars');

        $user = User::factory()->create(['avatar' => null]);
        $this->givenAnAuthenticatedUser($user);

        $this->deleteJson(route('profile.avatar.destroy'))
            ->assertOk()
            ->assertJsonPath('data.avatar_url', null);
    }

    public function test_it_requires_authentication(): void
    {
        $this->deleteJson(route('profile.avatar.destroy'))
            ->assertUnauthorized();
    }
}
