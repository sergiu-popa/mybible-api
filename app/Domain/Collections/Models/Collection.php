<?php

declare(strict_types=1);

namespace App\Domain\Collections\Models;

use App\Domain\Collections\QueryBuilders\CollectionQueryBuilder;
use Database\Factories\CollectionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $language
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read EloquentCollection<int, CollectionTopic> $topics
 */
#[UseFactory(CollectionFactory::class)]
final class Collection extends Model
{
    /** @use HasFactory<CollectionFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<CollectionTopic, $this>
     */
    public function topics(): HasMany
    {
        return $this->hasMany(CollectionTopic::class)->orderBy('position');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return CollectionQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new CollectionQueryBuilder($query);
    }
}
