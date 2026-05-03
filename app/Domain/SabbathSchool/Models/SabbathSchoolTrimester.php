<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Models;

use App\Domain\SabbathSchool\QueryBuilders\SabbathSchoolTrimesterQueryBuilder;
use Database\Factories\SabbathSchoolTrimesterFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $year
 * @property string $language
 * @property string $age_group
 * @property string $title
 * @property int $number
 * @property Carbon $date_from
 * @property Carbon $date_to
 * @property string|null $image_cdn_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SabbathSchoolLesson> $lessons
 */
#[UseFactory(SabbathSchoolTrimesterFactory::class)]
final class SabbathSchoolTrimester extends Model
{
    /** @use HasFactory<SabbathSchoolTrimesterFactory> */
    use HasFactory;

    protected $table = 'sabbath_school_trimesters';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'string',
            'age_group' => 'string',
            'number' => 'integer',
            'date_from' => 'date',
            'date_to' => 'date',
        ];
    }

    /**
     * @return HasMany<SabbathSchoolLesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(SabbathSchoolLesson::class, 'trimester_id')
            ->orderBy('date_from');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return SabbathSchoolTrimesterQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new SabbathSchoolTrimesterQueryBuilder($query);
    }
}
