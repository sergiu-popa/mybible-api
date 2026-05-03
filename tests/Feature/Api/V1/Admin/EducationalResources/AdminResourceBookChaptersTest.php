<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\EducationalResources;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Models\ResourceBookChapter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminResourceBookChaptersTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuper(): User
    {
        $user = User::factory()->super()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    public function test_list_chapters_returns_paginated_collection(): void
    {
        $this->actingAsSuper();

        $book = ResourceBook::factory()->create();
        ResourceBookChapter::factory()->forBook($book)->create(['position' => 1]);
        ResourceBookChapter::factory()->forBook($book)->create(['position' => 2]);

        $this->getJson(route('admin.resource-books.chapters.index', ['book' => $book->id]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_create_appends_to_position_when_not_provided(): void
    {
        $this->actingAsSuper();

        $book = ResourceBook::factory()->create();
        ResourceBookChapter::factory()->forBook($book)->create(['position' => 1]);

        $this->postJson(route('admin.resource-books.chapters.store', ['book' => $book->id]), [
            'title' => 'New chapter',
            'content' => 'Body',
        ])->assertCreated()
            ->assertJsonPath('data.position', 2);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuper();

        $book = ResourceBook::factory()->create();

        $this->postJson(route('admin.resource-books.chapters.store', ['book' => $book->id]), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'content']);
    }

    public function test_update_chapter(): void
    {
        $this->actingAsSuper();

        $book = ResourceBook::factory()->create();
        $chapter = ResourceBookChapter::factory()->forBook($book)->create(['position' => 1]);

        $this->patchJson(route('admin.resource-book-chapters.update', ['chapter' => $chapter->id]), [
            'title' => 'Updated',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Updated');
    }

    public function test_delete_chapter(): void
    {
        $this->actingAsSuper();

        $book = ResourceBook::factory()->create();
        $chapter = ResourceBookChapter::factory()->forBook($book)->create(['position' => 1]);

        $this->deleteJson(route('admin.resource-book-chapters.destroy', ['chapter' => $chapter->id]))
            ->assertNoContent();

        $this->assertDatabaseMissing('resource_book_chapters', ['id' => $chapter->id]);
    }

    public function test_reorder_chapters_within_book(): void
    {
        $this->actingAsSuper();

        $book = ResourceBook::factory()->create();
        $a = ResourceBookChapter::factory()->forBook($book)->create(['position' => 1]);
        $b = ResourceBookChapter::factory()->forBook($book)->create(['position' => 2]);

        $this->postJson(route('admin.resource-books.chapters.reorder', ['book' => $book->id]), [
            'ids' => [$b->id, $a->id],
        ])->assertOk();

        $this->assertSame(1, (int) $b->fresh()?->position);
        $this->assertSame(2, (int) $a->fresh()?->position);
    }

    public function test_reorder_handles_partial_list_without_collision(): void
    {
        $this->actingAsSuper();

        $book = ResourceBook::factory()->create();
        $c1 = ResourceBookChapter::factory()->forBook($book)->create(['position' => 1]);
        $c2 = ResourceBookChapter::factory()->forBook($book)->create(['position' => 2]);
        $c3 = ResourceBookChapter::factory()->forBook($book)->create(['position' => 3]);
        $c4 = ResourceBookChapter::factory()->forBook($book)->create(['position' => 4]);

        $this->postJson(route('admin.resource-books.chapters.reorder', ['book' => $book->id]), [
            'ids' => [$c2->id, $c1->id, $c3->id],
        ])->assertOk();

        $this->assertSame(1, (int) $c2->fresh()?->position);
        $this->assertSame(2, (int) $c1->fresh()?->position);
        $this->assertSame(3, (int) $c3->fresh()?->position);
        $this->assertSame(4, (int) $c4->fresh()?->position);
    }

    public function test_reorder_rejects_cross_book_ids(): void
    {
        $this->actingAsSuper();

        $bookA = ResourceBook::factory()->create();
        $bookB = ResourceBook::factory()->create();
        $a = ResourceBookChapter::factory()->forBook($bookA)->create(['position' => 1]);
        $b = ResourceBookChapter::factory()->forBook($bookB)->create(['position' => 1]);

        $this->postJson(route('admin.resource-books.chapters.reorder', ['book' => $bookA->id]), [
            'ids' => [$a->id, $b->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);
    }
}
