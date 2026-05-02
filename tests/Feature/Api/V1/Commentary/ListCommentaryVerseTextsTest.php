<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Commentary;

use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListCommentaryVerseTextsTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_blocks_covering_the_verse(): void
    {
        $commentary = Commentary::factory()->published()->create();

        $singleVerse = CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 5,
            'verse_from' => 5,
            'verse_to' => 5,
            'verse_label' => '5',
        ]);

        $multiVerse = CommentaryText::factory()->forVerseRange(1, 7)->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 1,
        ]);

        // Block that does NOT cover verse 5.
        CommentaryText::factory()->forVerseRange(8, 10)->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 8,
        ]);

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('commentaries.verse', [
                'commentary' => $commentary->slug,
                'book' => 'GEN',
                'chapter' => 1,
                'verse' => 5,
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        /** @var list<array{position: int}> $rows */
        $rows = $response->json('data');
        $positions = array_column($rows, 'position');
        $this->assertContains($singleVerse->position, $positions);
        $this->assertContains($multiVerse->position, $positions);
    }

    public function test_it_returns_empty_when_no_block_covers_the_verse(): void
    {
        $commentary = Commentary::factory()->published()->create();

        CommentaryText::factory()->forVerseRange(8, 10)->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 8,
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('commentaries.verse', [
                'commentary' => $commentary->slug,
                'book' => 'GEN',
                'chapter' => 1,
                'verse' => 1,
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_it_returns_404_for_unpublished_commentary(): void
    {
        $commentary = Commentary::factory()->draft()->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('commentaries.verse', [
                'commentary' => $commentary->slug,
                'book' => 'GEN',
                'chapter' => 1,
                'verse' => 1,
            ]))
            ->assertNotFound();
    }
}
