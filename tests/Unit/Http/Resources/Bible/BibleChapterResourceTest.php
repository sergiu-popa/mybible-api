<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Bible;

use App\Domain\Bible\Models\BibleChapter;
use App\Http\Resources\Bible\BibleChapterResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class BibleChapterResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_number_and_verse_count(): void
    {
        $chapter = BibleChapter::factory()->create([
            'number' => 3,
            'verse_count' => 21,
        ]);

        $payload = (new BibleChapterResource($chapter))->toArray(Request::create('/'));

        $this->assertSame([
            'number' => 3,
            'verse_count' => 21,
        ], $payload);
    }
}
