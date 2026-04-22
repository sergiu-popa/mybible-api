<?php

declare(strict_types=1);

namespace App\Domain\Bible\Models;

use App\Domain\Bible\QueryBuilders\BibleVersionQueryBuilder;
use Database\Factories\BibleVersionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $abbreviation
 * @property string $language
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, BibleVerse> $verses
 */
#[UseFactory(BibleVersionFactory::class)]
final class BibleVersion extends Model
{
    /** @use HasFactory<BibleVersionFactory> */
    use HasFactory;

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'abbreviation';
    }

    /**
     * @return HasMany<BibleVerse, $this>
     */
    public function verses(): HasMany
    {
        return $this->hasMany(BibleVerse::class);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return BibleVersionQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new BibleVersionQueryBuilder($query);
    }
}
