<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\EducationalResources;

use App\Domain\Analytics\Models\ResourceDownload;
use App\Domain\EducationalResources\Models\ResourceBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class RecordResourceBookDownloadTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
        RateLimiter::clear('downloads');
    }

    public function test_records_a_book_download(): void
    {
        $book = ResourceBook::factory()->published()->create();

        $this->withHeaders($this->apiKeyHeaders() + ['X-Device-Id' => 'dev-1'])
            ->postJson(route('resource-books.downloads.store', ['book' => $book->slug]), [])
            ->assertNoContent();

        $this->assertDatabaseHas('resource_downloads', [
            'downloadable_type' => ResourceDownload::TYPE_RESOURCE_BOOK,
            'downloadable_id' => $book->id,
            'device_id' => 'dev-1',
        ]);
    }

    public function test_returns_404_for_draft_book(): void
    {
        $book = ResourceBook::factory()->draft()->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->postJson(route('resource-books.downloads.store', ['book' => $book->slug]), [])
            ->assertNotFound();

        $this->assertDatabaseMissing('resource_downloads', [
            'downloadable_id' => $book->id,
        ]);
    }
}
