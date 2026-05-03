<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Models;

use Database\Factories\SabbathSchoolSegmentContentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $segment_id
 * @property string $type
 * @property string|null $title
 * @property int $position
 * @property string $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SabbathSchoolSegment $segment
 */
#[UseFactory(SabbathSchoolSegmentContentFactory::class)]
final class SabbathSchoolSegmentContent extends Model
{
    /** @use HasFactory<SabbathSchoolSegmentContentFactory> */
    use HasFactory;

    protected $table = 'sabbath_school_segment_contents';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'segment_id' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SabbathSchoolSegment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(SabbathSchoolSegment::class, 'segment_id');
    }
}
