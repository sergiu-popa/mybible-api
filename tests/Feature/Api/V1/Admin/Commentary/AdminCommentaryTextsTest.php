<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Commentary;

use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminCommentaryTextsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuper(): User
    {
        $user = User::factory()->super()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    public function test_list_returns_texts_for_a_book_chapter_paginated(): void
    {
        $this->actingAsSuper();

        $commentary = Commentary::factory()->create();

        CommentaryText::factory()->count(3)->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
        ]);
        CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 2,
        ]);

        $this->getJson(route('admin.commentaries.texts.index', [
            'commentary' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
        ]))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'commentary_id', 'book', 'chapter', 'position', 'content']],
                'meta' => ['per_page', 'current_page', 'total'],
            ]);
    }

    public function test_create_persists_a_text_block(): void
    {
        $this->actingAsSuper();

        $commentary = Commentary::factory()->create();

        $this->postJson(route('admin.commentaries.texts.store', ['commentary' => $commentary->id]), [
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 1,
            'verse_from' => 1,
            'verse_to' => 3,
            'verse_label' => '1-3',
            'content' => 'Some commentary content.',
        ])->assertCreated()
            ->assertJsonPath('data.book', 'GEN')
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.verse_label', '1-3');

        $this->assertDatabaseHas('commentary_texts', [
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 1,
        ]);
    }

    public function test_create_rejects_duplicate_position_within_chapter(): void
    {
        $this->actingAsSuper();

        $commentary = Commentary::factory()->create();
        CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 5,
        ]);

        $this->postJson(route('admin.commentaries.texts.store', ['commentary' => $commentary->id]), [
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 5,
            'content' => 'Conflict',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['position']);
    }

    public function test_update_modifies_a_text_block(): void
    {
        $this->actingAsSuper();

        $commentary = Commentary::factory()->create();
        $text = CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 1,
            'content' => 'old',
        ]);

        $this->patchJson(route('admin.commentaries.texts.update', [
            'commentary' => $commentary->id,
            'text' => $text->id,
        ]), ['content' => 'new'])
            ->assertOk()
            ->assertJsonPath('data.content', 'new');
    }

    public function test_update_404s_for_text_belonging_to_a_different_commentary(): void
    {
        $this->actingAsSuper();

        $commentary = Commentary::factory()->create();
        $other = Commentary::factory()->create();
        $text = CommentaryText::factory()->create(['commentary_id' => $other->id]);

        $this->patchJson(route('admin.commentaries.texts.update', [
            'commentary' => $commentary->id,
            'text' => $text->id,
        ]), ['content' => 'new'])
            ->assertNotFound();
    }

    public function test_destroy_removes_a_text(): void
    {
        $this->actingAsSuper();

        $commentary = Commentary::factory()->create();
        $text = CommentaryText::factory()->create(['commentary_id' => $commentary->id]);

        $this->deleteJson(route('admin.commentaries.texts.destroy', [
            'commentary' => $commentary->id,
            'text' => $text->id,
        ]))->assertNoContent();

        $this->assertDatabaseMissing('commentary_texts', ['id' => $text->id]);
    }

    public function test_destroy_404s_for_cross_commentary_text(): void
    {
        $this->actingAsSuper();

        $commentary = Commentary::factory()->create();
        $other = Commentary::factory()->create();
        $text = CommentaryText::factory()->create(['commentary_id' => $other->id]);

        $this->deleteJson(route('admin.commentaries.texts.destroy', [
            'commentary' => $commentary->id,
            'text' => $text->id,
        ]))->assertNotFound();
    }

    public function test_reorder_settles_positions_in_provided_order(): void
    {
        $this->actingAsSuper();

        $commentary = Commentary::factory()->create();

        $first = CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 1,
        ]);
        $second = CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 2,
        ]);
        $third = CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 3,
        ]);

        $this->postJson(route('admin.commentaries.texts.reorder', ['commentary' => $commentary->id]), [
            'book' => 'GEN',
            'chapter' => 1,
            'ids' => [$third->id, $first->id, $second->id],
        ])->assertOk();

        $this->assertSame(1, $third->fresh()?->position);
        $this->assertSame(2, $first->fresh()?->position);
        $this->assertSame(3, $second->fresh()?->position);
    }

    public function test_reorder_rejects_ids_from_a_different_book_chapter(): void
    {
        $this->actingAsSuper();

        $commentary = Commentary::factory()->create();

        $genesis = CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 1,
        ]);
        $exodus = CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'book' => 'EXO',
            'chapter' => 1,
            'position' => 1,
        ]);

        $this->postJson(route('admin.commentaries.texts.reorder', ['commentary' => $commentary->id]), [
            'book' => 'GEN',
            'chapter' => 1,
            'ids' => [$genesis->id, $exodus->id],
        ])->assertUnprocessable();
    }
}
