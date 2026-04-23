<?php

declare(strict_types=1);

namespace App\Domain\Hymnal\Models;

use App\Domain\Hymnal\QueryBuilders\HymnalSongQueryBuilder;
use Database\Factories\HymnalSongFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $hymnal_book_id
 * @property int|null $number
 * @property array<string, string> $title
 * @property array<string, string>|null $author
 * @property array<string, string>|null $composer
 * @property array<string, string>|null $copyright
 * @property array<string, list<array{index: int, text: string, is_chorus: bool}>> $stanzas
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read HymnalBook $book
 */
#[UseFactory(HymnalSongFactory::class)]
final class HymnalSong extends Model
{
    /** @use HasFactory<HymnalSongFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => 'array',
            'author' => 'array',
            'composer' => 'array',
            'copyright' => 'array',
            'stanzas' => 'array',
        ];
    }

    /**
     * @return BelongsTo<HymnalBook, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(HymnalBook::class, 'hymnal_book_id');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return HymnalSongQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new HymnalSongQueryBuilder($query);
    }
}
