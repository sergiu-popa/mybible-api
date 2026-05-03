<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\EducationalResources;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Models\ResourceBookChapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ShowResourceBookChapterTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_full_chapter_content(): void
    {
        $book = ResourceBook::factory()->published()->create();
        $chapter = ResourceBookChapter::factory()->forBook($book)->create([
            'position' => 1,
            'title' => 'Chapter One',
            'content' => 'Lorem ipsum dolor.',
            'audio_cdn_url' => 'https://cdn.example.com/a.mp3',
            'duration_seconds' => 600,
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-books.chapters.show', [
                'book' => $book->slug,
                'chapter' => $chapter->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.id', $chapter->id)
            ->assertJsonPath('data.title', 'Chapter One')
            ->assertJsonPath('data.content', 'Lorem ipsum dolor.')
            ->assertJsonPath('data.audio_cdn_url', 'https://cdn.example.com/a.mp3')
            ->assertJsonPath('data.duration_seconds', 600);
    }

    public function test_it_returns_404_for_chapter_in_draft_book(): void
    {
        $book = ResourceBook::factory()->draft()->create();
        $chapter = ResourceBookChapter::factory()->forBook($book)->create(['position' => 1]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-books.chapters.show', [
                'book' => $book->slug,
                'chapter' => $chapter->id,
            ]))
            ->assertNotFound();
    }

    public function test_it_returns_404_for_chapter_from_different_book(): void
    {
        $bookA = ResourceBook::factory()->published()->create();
        $bookB = ResourceBook::factory()->published()->create();
        $chapterB = ResourceBookChapter::factory()->forBook($bookB)->create(['position' => 1]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-books.chapters.show', [
                'book' => $bookA->slug,
                'chapter' => $chapterB->id,
            ]))
            ->assertNotFound();
    }

    public function test_it_sets_short_cache_headers(): void
    {
        $book = ResourceBook::factory()->published()->create();
        $chapter = ResourceBookChapter::factory()->forBook($book)->create(['position' => 1]);

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-books.chapters.show', [
                'book' => $book->slug,
                'chapter' => $chapter->id,
            ]));

        $response->assertOk();
        $cc = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=600', $cc);
    }
}
