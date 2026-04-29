<?php

declare(strict_types=1);

namespace App\Domain\Bible\Support;

use App\Domain\Bible\Models\BibleVerse;
use App\Domain\Bible\Models\BibleVersion;

/**
 * Buffered counterpart to {@see BibleVersionExporter::stream()}. Builds the
 * full version-export JSON as a single string so the result fits in the
 * application cache. The streaming variant remains available for callers
 * that don't go through cache.
 */
final class BibleExportBuilder
{
    public function build(BibleVersion $version): string
    {
        $out = '';

        $out .= '{';
        $out .= '"version":' . $this->json([
            'id' => $version->id,
            'name' => $version->name,
            'abbreviation' => $version->abbreviation,
            'language' => $version->language,
        ]);

        $out .= ',"books":[';

        $currentBookId = null;
        $currentChapter = null;
        $firstBook = true;
        $firstChapter = true;
        $firstVerse = true;

        $query = BibleVerse::query()
            ->join('bible_books', 'bible_books.id', '=', 'bible_verses.bible_book_id')
            ->where('bible_verses.bible_version_id', $version->id)
            ->orderBy('bible_books.position')
            ->orderBy('bible_verses.chapter')
            ->orderBy('bible_verses.verse')
            ->select([
                'bible_verses.*',
                'bible_books.abbreviation as book_abbreviation',
                'bible_books.position as book_position',
            ]);

        foreach ($query->lazy() as $verse) {
            if ($verse->bible_book_id !== $currentBookId) {
                if (! $firstBook) {
                    $out .= ']}]}';
                }

                $currentBookId = $verse->bible_book_id;
                $currentChapter = null;
                $firstChapter = true;
                $firstVerse = true;

                $out .= ($firstBook ? '' : ',') . '{';
                $out .= '"id":' . $verse->bible_book_id;
                $out .= ',"abbreviation":' . $this->json((string) $verse->getAttribute('book_abbreviation'));
                $out .= ',"position":' . (int) $verse->getAttribute('book_position');
                $out .= ',"chapters":[';

                $firstBook = false;
            }

            if ($verse->chapter !== $currentChapter) {
                if (! $firstChapter) {
                    $out .= ']}';
                }

                $currentChapter = $verse->chapter;
                $firstVerse = true;

                $out .= ($firstChapter ? '' : ',') . '{';
                $out .= '"number":' . $verse->chapter;
                $out .= ',"verses":[';

                $firstChapter = false;
            }

            $out .= ($firstVerse ? '' : ',') . $this->json([
                'verse' => $verse->verse,
                'text' => $verse->text,
            ]);
            $firstVerse = false;
        }

        if (! $firstBook) {
            $out .= ']}]}';
        }

        $out .= ']}';

        return $out;
    }

    private function json(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
