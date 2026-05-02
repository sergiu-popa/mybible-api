<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Verses;

use App\Domain\Bible\Models\BibleBook;
use App\Domain\Bible\Models\BibleChapter;
use App\Domain\Bible\Models\BibleVerse;
use App\Domain\Bible\Models\BibleVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ResolveCrossChapterVersesTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    private BibleVersion $version;

    private BibleBook $book;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();

        $this->version = BibleVersion::factory()->romanian()->create();
        $this->book = BibleBook::factory()->create([
            'abbreviation' => 'MAT',
            'testament' => 'new',
            'position' => 40,
            'chapter_count' => 28,
            'names' => ['ro' => 'Matei', 'en' => 'Matthew', 'hu' => 'Máté'],
            'short_names' => ['ro' => 'Mat', 'en' => 'Mat', 'hu' => 'Mt'],
        ]);

        BibleChapter::query()->create([
            'bible_book_id' => $this->book->id,
            'number' => 19,
            'verse_count' => 30,
        ]);

        BibleChapter::query()->create([
            'bible_book_id' => $this->book->id,
            'number' => 20,
            'verse_count' => 34,
        ]);

        $this->seedVerses(19, range(27, 30));
        $this->seedVerses(20, range(1, 16));
    }

    public function test_it_returns_a_flat_verse_array_for_a_cross_chapter_range(): void
    {
        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('verses.index', ['reference' => 'MAT.19:27-20:16.VDC']));

        // 19:27..30 (4) + 20:1..16 (16) = 20 verses
        $response
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('data.0.chapter', 19)
            ->assertJsonPath('data.0.verse', 27)
            ->assertJsonPath('data.3.chapter', 19)
            ->assertJsonPath('data.3.verse', 30)
            ->assertJsonPath('data.4.chapter', 20)
            ->assertJsonPath('data.4.verse', 1)
            ->assertJsonPath('data.19.chapter', 20)
            ->assertJsonPath('data.19.verse', 16)
            ->assertJsonPath('meta.missing', []);
    }

    public function test_it_reports_missing_verses_within_a_cross_chapter_range(): void
    {
        BibleVerse::query()
            ->where('bible_version_id', $this->version->id)
            ->where('bible_book_id', $this->book->id)
            ->where('chapter', 20)
            ->where('verse', 5)
            ->delete();

        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('verses.index', ['reference' => 'MAT.19:27-20:16.VDC']));

        $response
            ->assertOk()
            ->assertJsonCount(19, 'data')
            ->assertJsonPath('meta.missing', [
                ['version' => 'VDC', 'book' => 'MAT', 'chapter' => 20, 'verse' => 5],
            ]);
    }

    public function test_it_returns_422_when_range_exceeds_the_cap(): void
    {
        // Build a giant range > 500 verses by extending chapter counts.
        BibleChapter::query()->where('bible_book_id', $this->book->id)->where('number', 19)->update([
            'verse_count' => 600,
        ]);

        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('verses.index', ['reference' => 'MAT.19:1-20:34.VDC']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reference']);
    }

    /**
     * @param  array<int, int>  $verses
     */
    private function seedVerses(int $chapter, array $verses): void
    {
        foreach ($verses as $verse) {
            BibleVerse::factory()->create([
                'bible_version_id' => $this->version->id,
                'bible_book_id' => $this->book->id,
                'chapter' => $chapter,
                'verse' => $verse,
                'text' => sprintf('MAT %d:%d', $chapter, $verse),
            ]);
        }
    }
}
