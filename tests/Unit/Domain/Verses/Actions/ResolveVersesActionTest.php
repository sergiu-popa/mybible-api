<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Verses\Actions;

use App\Domain\Bible\Models\BibleBook;
use App\Domain\Bible\Models\BibleChapter;
use App\Domain\Bible\Models\BibleVerse;
use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Reference\Reference;
use App\Domain\Verses\Actions\ResolveVersesAction;
use App\Domain\Verses\DataTransferObjects\ResolveVersesData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ResolveVersesActionTest extends TestCase
{
    use RefreshDatabase;

    private BibleVersion $version;

    private BibleBook $genesis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->version = BibleVersion::factory()->romanian()->create();
        $this->genesis = BibleBook::factory()->genesis()->create();
    }

    public function test_it_resolves_all_verses_with_no_missing(): void
    {
        $this->seedVerses(chapter: 1, verses: [1, 2, 3]);

        $result = (new ResolveVersesAction)->handle(new ResolveVersesData(
            references: [new Reference('GEN', 1, [1, 2, 3], 'VDC')],
            version: 'VDC',
        ));

        $this->assertCount(3, $result->verses);
        $this->assertSame([], $result->missing);
    }

    public function test_it_reports_missing_verses(): void
    {
        $this->seedVerses(chapter: 1, verses: [1, 2]);

        $result = (new ResolveVersesAction)->handle(new ResolveVersesData(
            references: [new Reference('GEN', 1, [1, 2, 3, 4], 'VDC')],
            version: 'VDC',
        ));

        $this->assertCount(2, $result->verses);
        $this->assertEqualsCanonicalizing(
            [
                ['version' => 'VDC', 'book' => 'GEN', 'chapter' => 1, 'verse' => 3],
                ['version' => 'VDC', 'book' => 'GEN', 'chapter' => 1, 'verse' => 4],
            ],
            $result->missing,
        );
    }

    public function test_it_expands_whole_chapter_references_against_bible_chapters_verse_count(): void
    {
        BibleChapter::factory()->create([
            'bible_book_id' => $this->genesis->id,
            'number' => 1,
            'verse_count' => 3,
        ]);

        $this->seedVerses(chapter: 1, verses: [1, 2]);

        $result = (new ResolveVersesAction)->handle(new ResolveVersesData(
            references: [new Reference('GEN', 1, [], 'VDC')],
            version: 'VDC',
        ));

        $this->assertCount(2, $result->verses);
        $this->assertEquals(
            [['version' => 'VDC', 'book' => 'GEN', 'chapter' => 1, 'verse' => 3]],
            $result->missing,
        );
    }

    public function test_it_batches_queries_by_version_book_chapter(): void
    {
        $this->seedVerses(chapter: 1, verses: [1, 2]);
        $this->seedVerses(chapter: 2, verses: [1]);

        $queries = [];
        DB::listen(function ($event) use (&$queries): void {
            if (str_starts_with($event->sql, 'select * from `bible_verses`')) {
                $queries[] = $event->sql;
            }
        });

        $references = [
            new Reference('GEN', 1, [1], 'VDC'),
            new Reference('GEN', 1, [2], 'VDC'),
            new Reference('GEN', 2, [1], 'VDC'),
        ];

        (new ResolveVersesAction)->handle(new ResolveVersesData(
            references: $references,
            version: 'VDC',
        ));

        // Two distinct (version, book, chapter) groups → two bible_verses queries.
        $this->assertCount(2, $queries);
    }

    public function test_it_returns_empty_when_references_is_empty(): void
    {
        $result = (new ResolveVersesAction)->handle(new ResolveVersesData(
            references: [],
            version: 'VDC',
        ));

        $this->assertCount(0, $result->verses);
        $this->assertSame([], $result->missing);
    }

    /**
     * @param  array<int, int>  $verses
     */
    private function seedVerses(int $chapter, array $verses): void
    {
        foreach ($verses as $verse) {
            BibleVerse::factory()->create([
                'bible_version_id' => $this->version->id,
                'bible_book_id' => $this->genesis->id,
                'chapter' => $chapter,
                'verse' => $verse,
                'text' => sprintf('Ch %d V %d', $chapter, $verse),
            ]);
        }
    }
}
