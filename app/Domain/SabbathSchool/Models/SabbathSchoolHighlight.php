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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $sabbath_school_segment_id
 * @property int|null $segment_content_id
 * @property int|null $start_position
 * @property int|null $end_position
 * @property string|null $color
 * @property string|null $passage
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read SabbathSchoolSegment $segment
 * @property-read SabbathSchoolSegmentContent|null $segmentContent
 */
#[UseFactory(SabbathSchoolHighlightFactory::class)]
final class SabbathSchoolHighlight extends Model
{
    /** @use HasFactory<SabbathSchoolHighlightFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'segment_content_id' => 'integer',
            'start_position' => 'integer',
            'end_position' => 'integer',
        ];
    }

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
     * @return BelongsTo<SabbathSchoolSegmentContent, $this>
     */
    public function segmentContent(): BelongsTo
    {
        return $this->belongsTo(SabbathSchoolSegmentContent::class, 'segment_content_id');
    }

    /**
     * Scope route-model binding to the caller so cross-user PATCH
     * attempts 404 (the row is invisible to anyone else). Fail closed
     * if no user is authenticated so the gate is never silently lifted.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $userId = request()->user()?->id;

        if ($userId === null) {
            return null;
        }

        $field ??= $this->getRouteKeyName();

        return self::query()
            ->where($field, $value)
            ->where('user_id', $userId)
            ->first();
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
