<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Commentary;

use App\Domain\Commentary\Models\Commentary;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminCommentariesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuper(): User
    {
        $user = User::factory()->super()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    public function test_list_includes_drafts_for_super_admin(): void
    {
        $this->actingAsSuper();

        Commentary::factory()->draft()->forLanguage(Language::Ro)->create();
        Commentary::factory()->published()->forLanguage(Language::Ro)->create();

        $this->getJson(route('admin.commentaries.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'slug', 'name', 'abbreviation', 'language', 'is_published']],
            ]);
    }

    public function test_list_is_blocked_for_non_super_admin(): void
    {
        $this->actingAsAdmin();

        $this->getJson(route('admin.commentaries.index'))
            ->assertForbidden();
    }

    public function test_list_requires_authentication(): void
    {
        $this->getJson(route('admin.commentaries.index'))
            ->assertUnauthorized();
    }

    public function test_create_persists_a_new_commentary(): void
    {
        $this->actingAsSuper();

        $payload = [
            'slug' => 'sda-bible-commentary',
            'name' => ['en' => 'SDA Commentary', 'ro' => 'Comentariu SDA'],
            'abbreviation' => 'SDA',
            'language' => 'ro',
        ];

        $this->postJson(route('admin.commentaries.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'sda-bible-commentary')
            ->assertJsonPath('data.is_published', false);

        $this->assertDatabaseHas('commentaries', [
            'slug' => 'sda-bible-commentary',
            'abbreviation' => 'SDA',
            'language' => 'ro',
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuper();

        $this->postJson(route('admin.commentaries.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'abbreviation', 'language']);
    }

    public function test_create_rejects_duplicate_slug(): void
    {
        $this->actingAsSuper();

        Commentary::factory()->create(['slug' => 'taken-slug']);

        $this->postJson(route('admin.commentaries.store'), [
            'slug' => 'taken-slug',
            'name' => ['ro' => 'X'],
            'abbreviation' => 'X',
            'language' => 'ro',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_create_is_blocked_for_non_super(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.commentaries.store'), [
            'name' => ['ro' => 'X'],
            'abbreviation' => 'X',
            'language' => 'ro',
        ])->assertForbidden();
    }

    public function test_update_modifies_metadata(): void
    {
        $this->actingAsSuper();

        $commentary = Commentary::factory()->create(['abbreviation' => 'OLD']);

        $this->patchJson(route('admin.commentaries.update', ['commentary' => $commentary->id]), [
            'abbreviation' => 'NEW',
        ])->assertOk()
            ->assertJsonPath('data.abbreviation', 'NEW');

        $this->assertSame('NEW', $commentary->fresh()?->abbreviation);
    }

    public function test_update_rejects_slug_collision(): void
    {
        $this->actingAsSuper();

        $a = Commentary::factory()->create(['slug' => 'a']);
        $b = Commentary::factory()->create(['slug' => 'b']);

        $this->patchJson(route('admin.commentaries.update', ['commentary' => $a->id]), [
            'slug' => $b->slug,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_publish_and_unpublish_round_trip(): void
    {
        $this->actingAsSuper();

        $commentary = Commentary::factory()->draft()->create();

        $this->postJson(route('admin.commentaries.publish', ['commentary' => $commentary->id]))
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $this->assertTrue((bool) $commentary->fresh()->is_published);

        $this->postJson(route('admin.commentaries.unpublish', ['commentary' => $commentary->id]))
            ->assertOk()
            ->assertJsonPath('data.is_published', false);

        $this->assertFalse((bool) $commentary->fresh()->is_published);
    }
}
