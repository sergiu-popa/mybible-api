<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Commentary;

use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class CommentaryTextResourceFallbackTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpApiKeyClient();
    }

    public function test_public_reader_prefers_with_references_over_content_and_surfaces_counter(): void
    {
        $commentary = Commentary::factory()->published()->create();

        CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 1,
            'content' => '<p>legacy</p>',
            'plain' => '<p>plain</p>',
            'with_references' => '<p>with refs</p>',
            'errors_reported' => 2,
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('commentaries.chapter', [
                'commentary' => $commentary->slug,
                'book' => 'GEN',
                'chapter' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.content', '<p>with refs</p>')
            ->assertJsonPath('data.0.errors_reported', 2);
    }

    public function test_public_reader_falls_back_to_content_when_ai_columns_empty(): void
    {
        $commentary = Commentary::factory()->published()->create();

        CommentaryText::factory()->create([
            'commentary_id' => $commentary->id,
            'book' => 'GEN',
            'chapter' => 1,
            'position' => 1,
            'content' => '<p>legacy only</p>',
            'plain' => null,
            'with_references' => null,
            'original' => null,
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('commentaries.chapter', [
                'commentary' => $commentary->slug,
                'book' => 'GEN',
                'chapter' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.content', '<p>legacy only</p>');
    }
}
