<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class UploadUserAvatarTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    public function test_it_uploads_an_avatar_and_returns_the_resource(): void
    {
        $disk = Storage::fake('avatars');

        $user = User::factory()->create();
        $this->givenAnAuthenticatedUser($user);

        $response = $this->postJson(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $user->refresh();
        $this->assertNotNull($user->avatar);
        $disk->assertExists((string) $user->avatar);

        // avatar_url should not be null anymore.
        $this->assertNotNull($response->json('data.avatar_url'));
    }

    public function test_it_replaces_the_existing_avatar_and_deletes_the_old_file(): void
    {
        $disk = Storage::fake('avatars');

        $user = User::factory()->create();
        $this->givenAnAuthenticatedUser($user);

        $firstResponse = $this->postJson(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('first.png'),
        ])->assertOk();

        $firstPath = $user->refresh()->avatar;
        $this->assertIsString($firstPath);
        $disk->assertExists($firstPath);

        $this->postJson(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('second.png', 200, 200),
        ])->assertOk();

        $secondPath = $user->refresh()->avatar;
        $this->assertNotSame($firstPath, $secondPath);
        $disk->assertMissing($firstPath);
        $disk->assertExists((string) $secondPath);

        // The first response's returned url is superseded.
        $this->assertNotSame(
            $firstResponse->json('data.avatar_url'),
            $this->getJson(route('auth.me'))->json('data.avatar_url'),
        );
    }

    public function test_it_rejects_files_over_5_mb(): void
    {
        Storage::fake('avatars');

        $this->givenAnAuthenticatedUser();

        $this->postJson(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('large.png')->size(5 * 1024 + 1),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_it_rejects_non_jpeg_png_types(): void
    {
        Storage::fake('avatars');

        $this->givenAnAuthenticatedUser();

        $this->postJson(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('icon.gif'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_it_requires_authentication(): void
    {
        $this->postJson(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ])->assertUnauthorized();
    }
}
