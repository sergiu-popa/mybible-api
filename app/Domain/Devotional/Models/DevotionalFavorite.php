<?php

declare(strict_types=1);

namespace App\Domain\Devotional\Models;

use App\Domain\Devotional\QueryBuilders\DevotionalFavoriteQueryBuilder;
use App\Models\User;
use Database\Factories\DevotionalFavoriteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $devotional_id
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Devotional $devotional
 */
#[UseFactory(DevotionalFavoriteFactory::class)]
final class DevotionalFavorite extends Model
{
    /** @use HasFactory<DevotionalFavoriteFactory> */
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
     * @return BelongsTo<Devotional, $this>
     */
    public function devotional(): BelongsTo
    {
        return $this->belongsTo(Devotional::class);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return DevotionalFavoriteQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new DevotionalFavoriteQueryBuilder($query);
    }
}
