<?php

declare(strict_types=1);

namespace App\Domain\Verses\Models;

use App\Domain\Verses\QueryBuilders\DailyVerseQueryBuilder;
use Database\Factories\DailyVerseFactory;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps the shared Symfony `daily_verse` table. Columns: `id`, `for_date`
 * (date, unique), `reference` (varchar 25), `image_cdn_url` (text nullable).
 * No timestamps.
 *
 * @property int $id
 * @property DateTimeImmutable $for_date
 * @property ?string $language
 * @property string $reference
 * @property ?string $image_cdn_url
 */
#[UseFactory(DailyVerseFactory::class)]
final class DailyVerse extends Model
{
    /** @use HasFactory<DailyVerseFactory> */
    use HasFactory;

    protected $table = 'daily_verse';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'for_date' => 'immutable_date',
        ];
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return DailyVerseQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new DailyVerseQueryBuilder($query);
    }
}
