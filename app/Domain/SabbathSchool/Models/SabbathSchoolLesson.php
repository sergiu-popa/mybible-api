<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Models;

use App\Domain\SabbathSchool\QueryBuilders\SabbathSchoolLessonQueryBuilder;
use Database\Factories\SabbathSchoolLessonFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $trimester_id
 * @property string $language
 * @property string $age_group
 * @property int $number
 * @property string $title
 * @property string|null $memory_verse
 * @property string|null $image_cdn_url
 * @property Carbon $date_from
 * @property Carbon $date_to
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, SabbathSchoolSegment> $segments
 * @property-read SabbathSchoolTrimester|null $trimester
 */
#[UseFactory(SabbathSchoolLessonFactory::class)]
final class SabbathSchoolLesson extends Model
{
    /** @use HasFactory<SabbathSchoolLessonFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'published_at' => 'datetime',
            'number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SabbathSchoolTrimester, $this>
     */
    public function trimester(): BelongsTo
    {
        return $this->belongsTo(SabbathSchoolTrimester::class, 'trimester_id');
    }

    /**
     * @return HasMany<SabbathSchoolSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(SabbathSchoolSegment::class)->orderBy('position');
    }

    /**
     * Public routes hide drafts via `published()`. Admin routes serve
     * drafts so super-admins can edit them; the route-name guard
     * mirrors the Commentary precedent.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();

        $query = self::query()->where($field, $value);

        if (! request()->routeIs('admin.*')) {
            $query->published()->withLessonDetail();
        }

        return $query->first();
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return SabbathSchoolLessonQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new SabbathSchoolLessonQueryBuilder($query);
    }
}
