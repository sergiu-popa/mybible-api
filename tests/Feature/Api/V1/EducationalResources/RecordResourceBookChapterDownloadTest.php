<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\EducationalResources;

use App\Domain\Analytics\Models\ResourceDownload;
use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Models\ResourceBookChapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class RecordResourceBookChapterDownloadTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
        RateLimiter::clear('downloads');
    }

    public function test_records_a_chapter_download(): void
    {
        $book = ResourceBook::factory()->published()->create();
        $chapter = ResourceBookChapter::factory()->forBook($book)->create(['position' => 1]);

        $this->withHeaders($this->apiKeyHeaders())
            ->postJson(route('resource-books.chapters.downloads.store', [
                'book' => $book->slug,
                'chapter' => $chapter->id,
            ]), [])
            ->assertNoContent();

        $this->assertDatabaseHas('resource_downloads', [
            'downloadable_type' => ResourceDownload::TYPE_RESOURCE_BOOK_CHAPTER,
            'downloadable_id' => $chapter->id,
        ]);
    }

    public function test_returns_404_for_chapter_in_different_book(): void
    {
        $bookA = ResourceBook::factory()->published()->create();
        $bookB = ResourceBook::factory()->published()->create();
        $chapterB = ResourceBookChapter::factory()->forBook($bookB)->create(['position' => 1]);

        $this->withHeaders($this->apiKeyHeaders())
            ->postJson(route('resource-books.chapters.downloads.store', [
                'book' => $bookA->slug,
                'chapter' => $chapterB->id,
            ]), [])
            ->assertNotFound();
    }
}
