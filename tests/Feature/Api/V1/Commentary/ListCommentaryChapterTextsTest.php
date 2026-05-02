<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Commentary;

use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListCommentaryChapterTextsTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_text_blocks_for_a_chapter_ordered_by_position(): void
    {
        $commentary = Commentary::factory()->published()->create();

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
            'position' => 5,
        ]);

        // Different chapter — must be excluded.
        CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 2,
            'position' => 1,
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('commentaries.chapter', [
                'commentary' => $commentary->slug,
                'book' => 'GEN',
                'chapter' => 1,
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'book',
                    'chapter',
                    'position',
                    'verse_from',
                    'verse_to',
                    'verse_label',
                    'content',
                ]],
            ])
            ->assertJsonPath('data.0.position', $first->position)
            ->assertJsonPath('data.1.position', $second->position);
    }

    public function test_it_returns_404_for_unpublished_commentary(): void
    {
        $commentary = Commentary::factory()->draft()->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('commentaries.chapter', [
                'commentary' => $commentary->slug,
                'book' => 'GEN',
                'chapter' => 1,
            ]))
            ->assertNotFound();
    }

    public function test_it_returns_404_for_unknown_commentary(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('commentaries.chapter', [
                'commentary' => 'missing',
                'book' => 'GEN',
                'chapter' => 1,
            ]))
            ->assertNotFound();
    }

    public function test_it_validates_book_abbreviation(): void
    {
        $commentary = Commentary::factory()->published()->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('commentaries.chapter', [
                'commentary' => $commentary->slug,
                'book' => 'XYZ',
                'chapter' => 1,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['book']);
    }

    public function test_it_rejects_missing_credentials(): void
    {
        $commentary = Commentary::factory()->published()->create();

        $this->getJson(route('commentaries.chapter', [
            'commentary' => $commentary->slug,
            'book' => 'GEN',
            'chapter' => 1,
        ]))->assertUnauthorized();
    }
}
