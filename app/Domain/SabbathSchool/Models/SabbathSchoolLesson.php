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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $language
 * @property string $title
 * @property Carbon $week_start
 * @property Carbon $week_end
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, SabbathSchoolSegment> $segments
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
            'week_start' => 'date',
            'week_end' => 'date',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SabbathSchoolSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(SabbathSchoolSegment::class)->orderBy('position');
    }

    /**
     * Restrict route-model binding to published lessons so draft ids 404
     * before controllers ever see them. Eager-loads the lesson detail
     * graph in the same query so controllers do not duplicate the
     * relation list — see {@see SabbathSchoolLessonQueryBuilder::withLessonDetail()}.
     * Mirrors the ReadingPlan precedent.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();

        return SabbathSchoolLesson::query()
            ->published()
            ->withLessonDetail()
            ->where($field, $value)
            ->first();
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
