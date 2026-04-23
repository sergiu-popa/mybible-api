<?php

declare(strict_types=1);

namespace App\Domain\Hymnal\Models;

use App\Domain\Hymnal\QueryBuilders\HymnalFavoriteQueryBuilder;
use App\Models\User;
use Database\Factories\HymnalFavoriteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $hymnal_song_id
 * @property Carbon|null $created_at
 * @property-read User $user
 * @property-read HymnalSong $song
 */
#[UseFactory(HymnalFavoriteFactory::class)]
final class HymnalFavorite extends Model
{
    /** @use HasFactory<HymnalFavoriteFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<HymnalSong, $this>
     */
    public function song(): BelongsTo
    {
        return $this->belongsTo(HymnalSong::class, 'hymnal_song_id');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return HymnalFavoriteQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new HymnalFavoriteQueryBuilder($query);
    }
}
