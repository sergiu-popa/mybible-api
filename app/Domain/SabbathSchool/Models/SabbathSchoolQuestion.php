<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Models;

use Database\Factories\SabbathSchoolQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sabbath_school_segment_id
 * @property int $position
 * @property string $prompt
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SabbathSchoolSegment $segment
 */
#[UseFactory(SabbathSchoolQuestionFactory::class)]
final class SabbathSchoolQuestion extends Model
{
    /** @use HasFactory<SabbathSchoolQuestionFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SabbathSchoolSegment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(SabbathSchoolSegment::class, 'sabbath_school_segment_id');
    }
}
