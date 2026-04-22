<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Bible\Models\BibleBook;
use App\Domain\Bible\Models\BibleChapter;
use App\Domain\Reference\Data\BibleBookCatalog;
use App\Domain\Reference\Formatter\Languages\EnglishFormatter;
use App\Domain\Reference\Formatter\Languages\HungarianFormatter;
use App\Domain\Reference\Formatter\Languages\LanguageFormatter;
use App\Domain\Reference\Formatter\Languages\RomanianFormatter;
use Illuminate\Database\Seeder;

final class BibleCanonSeeder extends Seeder
{
    /**
     * Position 1-39 are Old Testament books, 40-66 are New Testament.
     */
    private const OLD_TESTAMENT_COUNT = 39;

    public function run(): void
    {
        $formatters = [
            'en' => new EnglishFormatter,
            'ro' => new RomanianFormatter,
            'hu' => new HungarianFormatter,
        ];

        $position = 0;

        foreach (BibleBookCatalog::BOOKS as $abbreviation => $chapterCount) {
            $position++;

            $names = $this->localizedNames($abbreviation, $formatters);
            $shortNames = $this->placeholderShortNames($abbreviation, $formatters);

            $book = BibleBook::query()->updateOrCreate(
                ['abbreviation' => $abbreviation],
                [
                    'testament' => $position <= self::OLD_TESTAMENT_COUNT ? 'old' : 'new',
                    'position' => $position,
                    'chapter_count' => $chapterCount,
                    'names' => $names,
                    'short_names' => $shortNames,
                ],
            );

            for ($number = 1; $number <= $chapterCount; $number++) {
                BibleChapter::query()->updateOrCreate(
                    ['bible_book_id' => $book->id, 'number' => $number],
                    ['verse_count' => 0],
                );
            }
        }
    }

    /**
     * @param  array<string, LanguageFormatter>  $formatters
     * @return array<string, string>
     */
    private function localizedNames(string $abbreviation, array $formatters): array
    {
        $names = [];

        foreach ($formatters as $language => $formatter) {
            $names[$language] = $formatter->bookName($abbreviation);
        }

        return $names;
    }

    /**
     * Short names have no authoritative source yet; seed each language with the
     * canonical abbreviation so downstream consumers get a stable placeholder
     * rather than the long name. Replace once LanguageFormatter exposes short
     * forms (see plan risk note).
     *
     * @param  array<string, LanguageFormatter>  $formatters
     * @return array<string, string>
     */
    private function placeholderShortNames(string $abbreviation, array $formatters): array
    {
        $shortNames = [];

        foreach (array_keys($formatters) as $language) {
            $shortNames[$language] = $abbreviation;
        }

        return $shortNames;
    }
}
