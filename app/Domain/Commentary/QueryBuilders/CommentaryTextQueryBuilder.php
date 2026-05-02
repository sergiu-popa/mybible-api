<?php

declare(strict_types=1);

namespace App\Domain\Commentary\QueryBuilders;

use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<CommentaryText>
 */
final class CommentaryTextQueryBuilder extends Builder
{
    public function forBookChapter(string $book, int $chapter): self
    {
        return $this
            ->where('book', $book)
            ->where('chapter', $chapter);
    }

    public function coveringVerse(string $book, int $chapter, int $verse): self
    {
        return $this
            ->where('book', $book)
            ->where('chapter', $chapter)
            ->where('verse_from', '<=', $verse)
            ->where(function (Builder $query) use ($verse): void {
                $query
                    ->whereNull('verse_to')
                    ->orWhere('verse_to', '>=', $verse);
            });
    }
}
