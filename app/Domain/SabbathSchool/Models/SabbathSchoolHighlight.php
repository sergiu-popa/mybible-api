<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Models;

use App\Domain\SabbathSchool\QueryBuilders\SabbathSchoolHighlightQueryBuilder;
use App\Models\User;
use Database\Factories\SabbathSchoolHighlightFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $sabbath_school_segment_id
 * @property string $passage
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read SabbathSchoolSegment $segment
 */
#[UseFactory(SabbathSchoolHighlightFactory::class)]
final class SabbathSchoolHighlight extends Model
{
    /** @use HasFactory<SabbathSchoolHighlightFactory> */
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
     * @return BelongsTo<SabbathSchoolSegment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(SabbathSchoolSegment::class, 'sabbath_school_segment_id');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return SabbathSchoolHighlightQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new SabbathSchoolHighlightQueryBuilder($query);
    }
}
