<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Devotionals;

use App\Domain\Devotional\Models\Devotional;
use App\Domain\Devotional\Models\DevotionalType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminDevotionalTypesTest extends TestCase
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

    public function test_list_returns_seeded_types_for_super_admin(): void
    {
        $this->actingAsSuper();

        $this->getJson(route('admin.devotional-types.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'slug', 'title', 'position', 'language']],
            ])
            ->assertJsonFragment(['slug' => 'adults'])
            ->assertJsonFragment(['slug' => 'kids']);
    }

    public function test_list_is_blocked_for_non_super_admin(): void
    {
        $this->actingAsAdmin();

        $this->getJson(route('admin.devotional-types.index'))
            ->assertForbidden();
    }

    public function test_list_requires_authentication(): void
    {
        $this->getJson(route('admin.devotional-types.index'))
            ->assertUnauthorized();
    }

    public function test_create_persists_a_new_type(): void
    {
        $this->actingAsSuper();

        $this->postJson(route('admin.devotional-types.store'), [
            'slug' => 'youth',
            'title' => 'Youth',
            'position' => 3,
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'youth')
            ->assertJsonPath('data.title', 'Youth')
            ->assertJsonPath('data.position', 3);

        $this->assertDatabaseHas('devotional_types', ['slug' => 'youth', 'title' => 'Youth']);
    }

    public function test_create_rejects_duplicate_slug(): void
    {
        $this->actingAsSuper();

        $this->postJson(route('admin.devotional-types.store'), [
            'slug' => 'adults',
            'title' => 'Adults v2',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_create_rejects_invalid_slug_format(): void
    {
        $this->actingAsSuper();

        $this->postJson(route('admin.devotional-types.store'), [
            'slug' => 'Bad Slug!',
            'title' => 'X',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_update_changes_fields(): void
    {
        $this->actingAsSuper();

        $type = DevotionalType::factory()->create([
            'slug' => 'youth',
            'title' => 'Youth',
            'position' => 3,
        ]);

        $this->patchJson(route('admin.devotional-types.update', ['type' => $type->slug]), [
            'title' => 'Tinerii',
            'position' => 4,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Tinerii')
            ->assertJsonPath('data.position', 4);
    }

    public function test_destroy_blocks_when_devotionals_exist(): void
    {
        $this->actingAsSuper();

        $type = DevotionalType::factory()->create(['slug' => 'youth', 'title' => 'Youth']);
        Devotional::factory()->ofType($type)->create();

        $this->deleteJson(route('admin.devotional-types.destroy', ['type' => 'youth']))
            ->assertUnprocessable();

        $this->assertDatabaseHas('devotional_types', ['id' => $type->id]);
    }

    public function test_destroy_allowed_when_no_devotionals(): void
    {
        $this->actingAsSuper();

        DevotionalType::factory()->create(['slug' => 'youth', 'title' => 'Youth']);

        $this->deleteJson(route('admin.devotional-types.destroy', ['type' => 'youth']))
            ->assertNoContent();

        $this->assertDatabaseMissing('devotional_types', ['slug' => 'youth']);
    }

    public function test_reorder_persists_positions(): void
    {
        $this->actingAsSuper();

        $first = DevotionalType::query()->where('slug', 'adults')->whereNull('language')->firstOrFail();
        $second = DevotionalType::query()->where('slug', 'kids')->whereNull('language')->firstOrFail();
        $third = DevotionalType::factory()->create(['slug' => 'youth', 'title' => 'Youth', 'position' => 3]);

        $this->postJson(route('admin.devotional-types.reorder'), [
            'ids' => [$third->id, $first->id, $second->id],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Reordered.');

        $this->assertSame(1, DevotionalType::query()->whereKey($third->id)->value('position'));
        $this->assertSame(2, DevotionalType::query()->whereKey($first->id)->value('position'));
        $this->assertSame(3, DevotionalType::query()->whereKey($second->id)->value('position'));
    }
}
