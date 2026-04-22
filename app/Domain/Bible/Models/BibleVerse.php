<?php

declare(strict_types=1);

namespace App\Domain\Bible\Models;

use App\Domain\Bible\QueryBuilders\BibleVerseQueryBuilder;
use Database\Factories\BibleVerseFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bible_version_id
 * @property int $bible_book_id
 * @property int $chapter
 * @property int $verse
 * @property string $text
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read BibleVersion $version
 * @property-read BibleBook $book
 */
#[UseFactory(BibleVerseFactory::class)]
final class BibleVerse extends Model
{
    /** @use HasFactory<BibleVerseFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chapter' => 'integer',
            'verse' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BibleVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(BibleVersion::class, 'bible_version_id');
    }

    /**
     * @return BelongsTo<BibleBook, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(BibleBook::class, 'bible_book_id');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return BibleVerseQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new BibleVerseQueryBuilder($query);
    }
}
