<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\QueryBuilders;

use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Reference\ChapterRange;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<OlympiadQuestion>
 */
final class OlympiadQuestionQueryBuilder extends Builder
{
    public function forLanguage(Language $language): self
    {
        return $this->where('language', $language->value);
    }

    public function forBook(string $book): self
    {
        return $this->where('book', $book);
    }

    public function forChapterRange(ChapterRange $range): self
    {
        return $this
            ->where('chapters_from', $range->from)
            ->where('chapters_to', $range->to);
    }

    /**
     * Match questions belonging to a theme described by `(book, range, language)`.
     * Supports both range-mode rows and single-chapter-mode rows.
     */
    public function matchingTheme(string $book, ChapterRange $range, Language $language): self
    {
        return $this
            ->where('book', $book)
            ->where('language', $language->value)
            ->where(function (self $q) use ($range): void {
                $q->where(function (self $sub) use ($range): void {
                    $sub->where('chapters_from', $range->from)->where('chapters_to', $range->to);
                });

                if ($range->isSingleChapter()) {
                    $q->orWhere('chapter', $range->from);
                }
            });
    }

    /**
     * Project the distinct `(book, chapters_from, chapters_to, language)`
     * tuples present in `olympiad_questions`, with a `question_count`
     * aggregate. Skips chapter-only rows where chapters_from is NULL.
     */
    public function themes(): self
    {
        return $this
            ->whereNotNull('chapters_from')
            ->whereNotNull('chapters_to')
            ->select([
                'book',
                'chapters_from',
                'chapters_to',
                'language',
            ])
            ->selectRaw('COUNT(*) as question_count')
            ->groupBy('book', 'chapters_from', 'chapters_to', 'language')
            ->orderBy('language')
            ->orderBy('book')
            ->orderBy('chapters_from')
            ->orderBy('chapters_to');
    }
}
