<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\User\Profile\Actions;

use App\Domain\Shared\Enums\Language;
use App\Domain\User\Profile\Actions\UpdateUserProfileAction;
use App\Domain\User\Profile\DataTransferObjects\UpdateUserProfileData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UpdateUserProfileActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_all_fields_when_provided(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'language' => null,
            'preferred_version' => null,
        ]);

        $result = $this->app->make(UpdateUserProfileAction::class)->execute(
            $user,
            new UpdateUserProfileData(
                name: 'New Name',
                language: Language::Ro,
                preferredVersion: 'VDC',
            ),
        );

        $this->assertSame('New Name', $result->name);
        $this->assertSame('ro', $result->language);
        $this->assertSame('VDC', $result->preferred_version);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'language' => 'ro',
            'preferred_version' => 'VDC',
        ]);
    }

    public function test_it_leaves_non_supplied_fields_untouched(): void
    {
        $user = User::factory()->create([
            'name' => 'Original',
            'language' => 'en',
            'preferred_version' => 'KJV',
        ]);

        $this->app->make(UpdateUserProfileAction::class)->execute(
            $user,
            new UpdateUserProfileData(
                name: 'Changed',
                language: null,
                preferredVersion: null,
            ),
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Changed',
            'language' => 'en',
            'preferred_version' => 'KJV',
        ]);
    }

    public function test_it_casts_language_enum_to_its_string_value(): void
    {
        $user = User::factory()->create(['language' => null]);

        $result = $this->app->make(UpdateUserProfileAction::class)->execute(
            $user,
            new UpdateUserProfileData(
                name: null,
                language: Language::Hu,
                preferredVersion: null,
            ),
        );

        $this->assertSame('hu', $result->language);
    }
}
