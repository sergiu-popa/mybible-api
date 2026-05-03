<?php

declare(strict_types=1);

namespace App\Domain\Devotional\Models;

use App\Domain\Devotional\QueryBuilders\DevotionalTypeQueryBuilder;
use Database\Factories\DevotionalTypeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property int $position
 * @property ?string $language
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Devotional> $devotionals
 */
#[UseFactory(DevotionalTypeFactory::class)]
final class DevotionalType extends Model
{
    /** @use HasFactory<DevotionalTypeFactory> */
    use HasFactory;

    protected $table = 'devotional_types';

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
     * @return HasMany<Devotional, $this>
     */
    public function devotionals(): HasMany
    {
        return $this->hasMany(Devotional::class, 'type_id');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return DevotionalTypeQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new DevotionalTypeQueryBuilder($query);
    }
}
