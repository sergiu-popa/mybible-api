<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Models;

use App\Domain\SabbathSchool\QueryBuilders\SabbathSchoolFavoriteQueryBuilder;
use App\Models\User;
use Database\Factories\SabbathSchoolFavoriteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $sabbath_school_lesson_id
 * @property int $sabbath_school_segment_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read SabbathSchoolLesson $lesson
 */
#[UseFactory(SabbathSchoolFavoriteFactory::class)]
final class SabbathSchoolFavorite extends Model
{
    /** @use HasFactory<SabbathSchoolFavoriteFactory> */
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
     * @return BelongsTo<SabbathSchoolLesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(SabbathSchoolLesson::class, 'sabbath_school_lesson_id');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return SabbathSchoolFavoriteQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new SabbathSchoolFavoriteQueryBuilder($query);
    }
}
