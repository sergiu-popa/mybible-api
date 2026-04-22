<?php

declare(strict_types=1);

namespace App\Domain\Bible\Support;

use App\Domain\Bible\Models\BibleVerse;
use App\Domain\Bible\Models\BibleVersion;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BibleVersionExporter
{
    public function stream(BibleVersion $version): StreamedResponse
    {
        return new StreamedResponse(function () use ($version): void {
            $this->emit($version);
        });
    }

    private function emit(BibleVersion $version): void
    {
        $out = fopen('php://output', 'wb');
        if ($out === false) {
            return;
        }

        fwrite($out, '{');
        fwrite($out, '"version":' . $this->json([
            'id' => $version->id,
            'name' => $version->name,
            'abbreviation' => $version->abbreviation,
            'language' => $version->language,
        ]));

        fwrite($out, ',"books":[');

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
                    fwrite($out, ']}]}');
                }

                $currentBookId = $verse->bible_book_id;
                $currentChapter = null;
                $firstChapter = true;
                $firstVerse = true;

                fwrite($out, ($firstBook ? '' : ',') . '{');
                fwrite($out, '"id":' . $verse->bible_book_id);
                fwrite($out, ',"abbreviation":' . $this->json((string) $verse->getAttribute('book_abbreviation')));
                fwrite($out, ',"position":' . (int) $verse->getAttribute('book_position'));
                fwrite($out, ',"chapters":[');

                $firstBook = false;
            }

            if ($verse->chapter !== $currentChapter) {
                if (! $firstChapter) {
                    fwrite($out, ']}');
                }

                $currentChapter = $verse->chapter;
                $firstVerse = true;

                fwrite($out, ($firstChapter ? '' : ',') . '{');
                fwrite($out, '"number":' . $verse->chapter);
                fwrite($out, ',"verses":[');

                $firstChapter = false;
            }

            fwrite($out, ($firstVerse ? '' : ',') . $this->json([
                'verse' => $verse->verse,
                'text' => $verse->text,
            ]));
            $firstVerse = false;
        }

        if (! $firstBook) {
            fwrite($out, ']}]}');
        }

        fwrite($out, ']}');
        fclose($out);
    }

    private function json(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
