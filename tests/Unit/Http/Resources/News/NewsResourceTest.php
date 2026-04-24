<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\News;

use App\Domain\News\Models\News;
use App\Http\Resources\News\NewsResource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class NewsResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_the_ac_fields(): void
    {
        $news = News::factory()->create([
            'language' => 'ro',
            'title' => 'Hello',
            'summary' => 'Summary here',
            'content' => 'Full body',
            'image_path' => null,
            'published_at' => CarbonImmutable::create(2026, 4, 23, 10),
        ]);

        $array = NewsResource::make($news)->toArray(new Request);

        $this->assertSame($news->id, $array['id']);
        $this->assertSame('Hello', $array['title']);
        $this->assertSame('Summary here', $array['summary']);
        $this->assertSame('Full body', $array['content']);
        $this->assertSame('ro', $array['language']);
        $this->assertNull($array['image_url']);
        $this->assertIsString($array['published_at']);
        $this->assertStringStartsWith('2026-04-23', $array['published_at']);
    }

    public function test_it_emits_a_storage_url_when_image_path_is_present(): void
    {
        Storage::fake('public');

        $news = News::factory()->withImage('news/photo.jpg')->create();

        $array = NewsResource::make($news)->toArray(new Request);

        $this->assertIsString($array['image_url']);
        $this->assertStringContainsString('news/photo.jpg', $array['image_url']);
    }

    public function test_content_is_null_when_column_is_null(): void
    {
        $news = News::factory()->create(['content' => null]);

        $array = NewsResource::make($news)->toArray(new Request);

        $this->assertArrayHasKey('content', $array);
        $this->assertNull($array['content']);
    }
}
