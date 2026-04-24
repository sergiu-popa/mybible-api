<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Profile;

use App\Domain\Bible\Models\BibleVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\TestCase;

final class UpdateUserProfileTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;

    public function test_it_updates_every_supplied_field(): void
    {
        $user = $this->givenAnAuthenticatedUser(
            User::factory()->create([
                'name' => 'Old Name',
                'language' => 'en',
                'preferred_version' => null,
            ]),
        );

        $this->patchJson(route('profile.update'), [
            'name' => 'New Name',
            'language' => 'ro',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.language', 'ro')
            ->assertJsonPath('data.email', $user->email);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'language' => 'ro',
        ]);
    }

    public function test_it_accepts_a_partial_payload(): void
    {
        $user = $this->givenAnAuthenticatedUser(
            User::factory()->create([
                'name' => 'Original',
                'language' => 'en',
            ]),
        );

        $this->patchJson(route('profile.update'), [
            'name' => 'Just Name',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Just Name')
            ->assertJsonPath('data.language', 'en');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Just Name',
            'language' => 'en',
        ]);
    }

    public function test_it_rejects_an_empty_payload(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->patchJson(route('profile.update'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_it_rejects_unsupported_language(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->patchJson(route('profile.update'), [
            'language' => 'de',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['language']);
    }

    public function test_it_accepts_a_known_preferred_version(): void
    {
        $version = BibleVersion::factory()->create(['abbreviation' => 'VDC']);
        $user = $this->givenAnAuthenticatedUser();

        $this->patchJson(route('profile.update'), [
            'preferred_version' => $version->abbreviation,
        ])
            ->assertOk()
            ->assertJsonPath('data.preferred_version', 'VDC');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'preferred_version' => 'VDC',
        ]);
    }

    public function test_it_rejects_an_unknown_preferred_version(): void
    {
        $this->givenAnAuthenticatedUser();

        $this->patchJson(route('profile.update'), [
            'preferred_version' => 'DOES-NOT-EXIST',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preferred_version']);
    }

    public function test_it_requires_authentication(): void
    {
        $this->patchJson(route('profile.update'), ['name' => 'Foo'])
            ->assertUnauthorized();
    }
}
