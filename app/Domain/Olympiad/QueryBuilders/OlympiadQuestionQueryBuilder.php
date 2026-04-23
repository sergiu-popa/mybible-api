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
     * Project the distinct `(book, chapters_from, chapters_to, language)`
     * tuples present in `olympiad_questions`, with a `question_count`
     * aggregate. The caller is responsible for any `forLanguage()` filter
     * and pagination.
     */
    public function themes(): self
    {
        return $this
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
