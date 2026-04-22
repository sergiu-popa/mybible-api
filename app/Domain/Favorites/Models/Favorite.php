<?php

declare(strict_types=1);

namespace App\Domain\Favorites\Models;

use App\Domain\Favorites\QueryBuilders\FavoriteQueryBuilder;
use App\Models\User;
use Database\Factories\FavoriteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $category_id
 * @property string $reference
 * @property string|null $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read FavoriteCategory|null $category
 */
#[UseFactory(FavoriteFactory::class)]
final class Favorite extends Model
{
    /** @use HasFactory<FavoriteFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<FavoriteCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FavoriteCategory::class, 'category_id');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return FavoriteQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new FavoriteQueryBuilder($query);
    }
}
