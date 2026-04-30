<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\EducationalResources;

use App\Domain\EducationalResources\Models\EducationalResource;
use App\Domain\EducationalResources\Models\ResourceCategory;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReorderTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function test_categories_reorder_persists_full_ordering(): void
    {
        $this->actingAsAdmin();

        $a = ResourceCategory::factory()->forLanguage(Language::En)->create();
        $b = ResourceCategory::factory()->forLanguage(Language::En)->create();
        $c = ResourceCategory::factory()->forLanguage(Language::En)->create();

        $this->postJson(route('admin.resource-categories.reorder'), [
            'ids' => [$c->id, $a->id, $b->id],
        ])->assertOk();

        $this->assertSame(1, $c->refresh()->position);
        $this->assertSame(2, $a->refresh()->position);
        $this->assertSame(3, $b->refresh()->position);
    }

    public function test_categories_reorder_requires_authentication(): void
    {
        $this->postJson(route('admin.resource-categories.reorder'), ['ids' => [1]])
            ->assertUnauthorized();
    }

    public function test_categories_reorder_blocks_non_admin(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('admin.resource-categories.reorder'), ['ids' => [1]])
            ->assertForbidden();
    }

    public function test_categories_reorder_validates_ids_array(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.resource-categories.reorder'), ['ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);
    }

    public function test_resources_reorder_scopes_to_target_category(): void
    {
        $this->actingAsAdmin();

        $category = ResourceCategory::factory()->forLanguage(Language::En)->create();
        $other = ResourceCategory::factory()->forLanguage(Language::En)->create();

        $r1 = EducationalResource::factory()->forCategory($category)->create();
        $r2 = EducationalResource::factory()->forCategory($category)->create();
        $r3 = EducationalResource::factory()->forCategory($category)->create();
        $foreigner = EducationalResource::factory()->forCategory($other)->create([
            'position' => 99,
        ]);

        $this->postJson(
            route('admin.resource-categories.resources.reorder', ['category' => $category->id]),
            ['ids' => [$r3->id, $r1->id, $r2->id, $foreigner->id]],
        )->assertOk();

        $this->assertSame(1, $r3->refresh()->position);
        $this->assertSame(2, $r1->refresh()->position);
        $this->assertSame(3, $r2->refresh()->position);
        $this->assertSame(99, $foreigner->refresh()->position);
    }

    public function test_resources_reorder_blocks_non_admin(): void
    {
        $category = ResourceCategory::factory()->forLanguage(Language::En)->create();
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(
                route('admin.resource-categories.resources.reorder', ['category' => $category->id]),
                ['ids' => [1]],
            )
            ->assertForbidden();
    }
}
