<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Verses;

use App\Domain\Bible\Models\BibleBook;
use App\Domain\Bible\Models\BibleVerse;
use App\Domain\Bible\Models\BibleVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ResolveVersesTest extends TestCase
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
        $this->book = BibleBook::factory()->genesis()->create();

        $this->seedVerses([1, 2, 3, 4, 5]);
    }

    public function test_it_returns_a_single_verse_by_canonical_reference(): void
    {
        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('verses.index', ['reference' => 'GEN.1:1.VDC']));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [['version', 'book', 'chapter', 'verse', 'text']],
                'meta' => ['missing'],
            ])
            ->assertJsonPath('data.0.version', 'VDC')
            ->assertJsonPath('data.0.book', 'GEN')
            ->assertJsonPath('data.0.chapter', 1)
            ->assertJsonPath('data.0.verse', 1)
            ->assertJsonPath('meta.missing', []);
    }

    public function test_it_returns_a_verse_range(): void
    {
        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('verses.index', ['reference' => 'GEN.1:1-3.VDC']));

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.missing', []);
    }

    public function test_it_returns_mixed_verses(): void
    {
        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('verses.index', ['reference' => 'GEN.1:1-3,5.VDC']));

        $response
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.missing', []);
    }

    public function test_it_accepts_split_parameters(): void
    {
        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('verses.index', [
                'book' => 'GEN',
                'chapter' => 1,
                'verses' => '1-3,5',
                'version' => 'VDC',
            ]));

        $response
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_it_rejects_when_both_reference_and_split_params_are_provided(): void
    {
        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('verses.index', [
                'reference' => 'GEN.1:1.VDC',
                'book' => 'GEN',
                'chapter' => 1,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reference']);
    }

    public function test_it_rejects_when_neither_reference_nor_split_params_are_provided(): void
    {
        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('verses.index'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reference']);
    }

    public function test_it_returns_422_for_unknown_book(): void
    {
        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('verses.index', ['reference' => 'XYZ.1:1.VDC']));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reference']);
    }

    public function test_it_reports_partial_resolution_via_meta_missing(): void
    {
        // Verses 1-5 seeded; request 3-7 so 6,7 should end up missing.
        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('verses.index', ['reference' => 'GEN.1:3-7.VDC']));

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.missing', [
                ['version' => 'VDC', 'book' => 'GEN', 'chapter' => 1, 'verse' => 6],
                ['version' => 'VDC', 'book' => 'GEN', 'chapter' => 1, 'verse' => 7],
            ]);
    }

    public function test_it_falls_back_to_the_language_default_version(): void
    {
        config()->set('bible.default_version_by_language', ['ro' => 'VDC']);

        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('verses.index', [
                'book' => 'GEN',
                'chapter' => 1,
                'verses' => '1',
                'language' => 'ro',
            ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.0.version', 'VDC');
    }

    public function test_it_rejects_when_no_version_can_be_resolved(): void
    {
        config()->set('bible.default_version_by_language', []);

        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('verses.index', [
                'book' => 'GEN',
                'chapter' => 1,
                'verses' => '1',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['version']);
    }

    public function test_it_rejects_an_unknown_explicit_version(): void
    {
        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('verses.index', [
                'reference' => 'GEN.1:1.VDC',
                'version' => 'NOPE',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['version']);
    }

    public function test_it_rejects_missing_auth(): void
    {
        $this
            ->getJson(route('verses.index', ['reference' => 'GEN.1:1.VDC']))
            ->assertUnauthorized();
    }

    /**
     * @param  array<int, int>  $verses
     */
    private function seedVerses(array $verses): void
    {
        foreach ($verses as $verse) {
            BibleVerse::factory()->create([
                'bible_version_id' => $this->version->id,
                'bible_book_id' => $this->book->id,
                'chapter' => 1,
                'verse' => $verse,
                'text' => 'Verse ' . $verse,
            ]);
        }
    }
}
