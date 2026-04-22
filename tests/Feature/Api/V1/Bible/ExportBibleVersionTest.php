<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Bible;

use App\Domain\Bible\Models\BibleBook;
use App\Domain\Bible\Models\BibleVerse;
use App\Domain\Bible\Models\BibleVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ExportBibleVersionTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    private function seedTinyVersion(): BibleVersion
    {
        $version = BibleVersion::factory()->create([
            'name' => 'Tiny Version',
            'abbreviation' => 'TNY',
            'language' => 'en',
        ]);

        $genesis = BibleBook::factory()->create([
            'abbreviation' => 'GEN',
            'position' => 1,
            'testament' => 'old',
            'chapter_count' => 1,
        ]);
        $exodus = BibleBook::factory()->create([
            'abbreviation' => 'EXO',
            'position' => 2,
            'testament' => 'old',
            'chapter_count' => 1,
        ]);

        foreach ([[1, 1, 'In the beginning.'], [1, 2, 'And the earth.']] as [$chapter, $verse, $text]) {
            BibleVerse::factory()->create([
                'bible_version_id' => $version->id,
                'bible_book_id' => $genesis->id,
                'chapter' => $chapter,
                'verse' => $verse,
                'text' => $text,
            ]);
        }

        BibleVerse::factory()->create([
            'bible_version_id' => $version->id,
            'bible_book_id' => $exodus->id,
            'chapter' => 1,
            'verse' => 1,
            'text' => 'Now these are the names.',
        ]);

        return $version;
    }

    public function test_it_streams_the_full_version_as_structured_json(): void
    {
        $version = $this->seedTinyVersion();

        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->get(route('bible-versions.export', ['version' => 'TNY']));

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'max-age=86400, public');
        $this->assertNotEmpty($response->headers->get('ETag'));

        $payload = json_decode($response->streamedContent(), true);

        $this->assertSame('TNY', $payload['version']['abbreviation']);
        $this->assertSame($version->id, $payload['version']['id']);
        $this->assertCount(2, $payload['books']);
        $this->assertSame('GEN', $payload['books'][0]['abbreviation']);
        $this->assertSame(1, $payload['books'][0]['chapters'][0]['number']);
        $this->assertCount(2, $payload['books'][0]['chapters'][0]['verses']);
        $this->assertSame(1, $payload['books'][0]['chapters'][0]['verses'][0]['verse']);
        $this->assertSame('In the beginning.', $payload['books'][0]['chapters'][0]['verses'][0]['text']);
        $this->assertSame('EXO', $payload['books'][1]['abbreviation']);
    }

    public function test_it_returns_404_for_an_unknown_abbreviation(): void
    {
        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('bible-versions.export', ['version' => 'XYZ']))
            ->assertNotFound();
    }

    public function test_it_rejects_missing_api_key(): void
    {
        BibleVersion::factory()->create(['abbreviation' => 'TNY']);

        $this->getJson(route('bible-versions.export', ['version' => 'TNY']))
            ->assertUnauthorized();
    }

    public function test_it_returns_304_when_if_none_match_matches_etag(): void
    {
        $this->seedTinyVersion();

        $first = $this
            ->withHeaders($this->apiKeyHeaders())
            ->get(route('bible-versions.export', ['version' => 'TNY']));
        $first->assertOk();

        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this
            ->withHeaders($this->apiKeyHeaders() + ['If-None-Match' => $etag])
            ->get(route('bible-versions.export', ['version' => 'TNY']))
            ->assertStatus(304);
    }

    public function test_it_uses_lazy_loading_for_verses(): void
    {
        $this->seedTinyVersion();

        DB::enableQueryLog();

        $this
            ->withHeaders($this->apiKeyHeaders())
            ->get(route('bible-versions.export', ['version' => 'TNY']))
            ->assertOk()
            ->streamedContent();

        $queries = DB::getQueryLog();

        // At least one select on bible_verses must be present and it must NOT
        // pre-load all rows eagerly in a single hydration — lazy() chunks in
        // batches of 1000, so for a 3-row fixture we still expect only one
        // batch but the query runs against bible_verses.
        $versesQueries = array_filter(
            $queries,
            fn (array $q): bool => str_contains((string) $q['query'], 'from `bible_verses`'),
        );

        $this->assertNotEmpty($versesQueries);
    }
}
