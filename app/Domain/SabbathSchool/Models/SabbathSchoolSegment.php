<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Models;

use Database\Factories\SabbathSchoolSegmentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sabbath_school_lesson_id
 * @property int $day
 * @property string $title
 * @property string $content
 * @property array<int, string>|null $passages
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SabbathSchoolLesson $lesson
 * @property-read Collection<int, SabbathSchoolQuestion> $questions
 */
#[UseFactory(SabbathSchoolSegmentFactory::class)]
final class SabbathSchoolSegment extends Model
{
    /** @use HasFactory<SabbathSchoolSegmentFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'passages' => 'array',
            'day' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SabbathSchoolLesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(SabbathSchoolLesson::class, 'sabbath_school_lesson_id');
    }

    /**
     * @return HasMany<SabbathSchoolQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(SabbathSchoolQuestion::class, 'sabbath_school_segment_id')
            ->orderBy('position');
    }
}
