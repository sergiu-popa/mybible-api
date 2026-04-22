<?php

declare(strict_types=1);

namespace App\Domain\Hymnal\Models;

use App\Domain\Hymnal\QueryBuilders\HymnalBookQueryBuilder;
use Database\Factories\HymnalBookFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property array<string, string> $name
 * @property string $language
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, HymnalSong> $songs
 * @property int|null $songs_count
 */
#[UseFactory(HymnalBookFactory::class)]
final class HymnalBook extends Model
{
    /** @use HasFactory<HymnalBookFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<HymnalSong, $this>
     */
    public function songs(): HasMany
    {
        return $this->hasMany(HymnalSong::class)->orderBy('number');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return HymnalBookQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new HymnalBookQueryBuilder($query);
    }
}
