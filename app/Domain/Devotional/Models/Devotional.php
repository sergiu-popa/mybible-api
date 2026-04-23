<?php

declare(strict_types=1);

namespace App\Domain\Devotional\Models;

use App\Domain\Devotional\Enums\DevotionalType;
use App\Domain\Devotional\QueryBuilders\DevotionalQueryBuilder;
use Database\Factories\DevotionalFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $date
 * @property string $language
 * @property DevotionalType $type
 * @property string $title
 * @property string $content
 * @property ?string $passage
 * @property ?string $author
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, DevotionalFavorite> $favorites
 */
#[UseFactory(DevotionalFactory::class)]
final class Devotional extends Model
{
    /** @use HasFactory<DevotionalFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => DevotionalType::class,
        ];
    }

    /**
     * @return HasMany<DevotionalFavorite, $this>
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(DevotionalFavorite::class);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return DevotionalQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new DevotionalQueryBuilder($query);
    }
}
