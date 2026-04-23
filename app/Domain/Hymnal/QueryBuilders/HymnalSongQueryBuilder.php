<?php

declare(strict_types=1);

namespace App\Domain\Hymnal\QueryBuilders;

use App\Domain\Hymnal\Models\HymnalBook;
use App\Domain\Hymnal\Models\HymnalSong;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<HymnalSong>
 */
final class HymnalSongQueryBuilder extends Builder
{
    public function forBook(HymnalBook $book): self
    {
        return $this->where('hymnal_book_id', $book->id);
    }

    public function search(?string $query, Language $language): self
    {
        if ($query === null || trim($query) === '') {
            return $this;
        }

        $trimmed = trim($query);
        $jsonPath = '$."' . $language->value . '"';
        $connection = $this->getConnection();
        $driver = $connection instanceof Connection ? $connection->getDriverName() : '';

        // MySQL JSON_UNQUOTE + JSON_EXTRACT lets us LIKE-match against the
        // localised title value without pulling every row into PHP. The JSON
        // path flows through a bound parameter so the statement stays
        // binding-driven even if a non-enum locale value ever slips in.
        $titleExpression = $driver === 'mysql'
            ? 'JSON_UNQUOTE(JSON_EXTRACT(title, ?))'
            : 'JSON_EXTRACT(title, ?)';

        return $this->where(function (Builder $builder) use ($trimmed, $titleExpression, $jsonPath): void {
            $builder->whereRaw("{$titleExpression} LIKE ?", [$jsonPath, '%' . $trimmed . '%']);

            if (ctype_digit($trimmed)) {
                $builder->orWhere('number', (int) $trimmed);
            }
        });
    }
}
