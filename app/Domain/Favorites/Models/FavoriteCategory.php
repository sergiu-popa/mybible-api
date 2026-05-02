<?php

declare(strict_types=1);

namespace App\Domain\Favorites\Models;

use App\Domain\Favorites\QueryBuilders\FavoriteCategoryQueryBuilder;
use App\Models\User;
use Database\Factories\FavoriteCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $color
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Collection<int, Favorite> $favorites
 * @property int|null $favorites_count
 */
#[UseFactory(FavoriteCategoryFactory::class)]
final class FavoriteCategory extends Model
{
    /** @use HasFactory<FavoriteCategoryFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Favorite, $this>
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class, 'category_id');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return FavoriteCategoryQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new FavoriteCategoryQueryBuilder($query);
    }
}
