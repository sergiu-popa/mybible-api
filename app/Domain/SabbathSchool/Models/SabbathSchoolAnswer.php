<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Models;

use App\Domain\SabbathSchool\QueryBuilders\SabbathSchoolAnswerQueryBuilder;
use App\Models\User;
use Database\Factories\SabbathSchoolAnswerFactory;
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
 * @property int|null $segment_content_id
 * @property string $content
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read SabbathSchoolSegmentContent|null $segmentContent
 */
#[UseFactory(SabbathSchoolAnswerFactory::class)]
final class SabbathSchoolAnswer extends Model
{
    /** @use HasFactory<SabbathSchoolAnswerFactory> */
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
     * @return BelongsTo<SabbathSchoolSegmentContent, $this>
     */
    public function segmentContent(): BelongsTo
    {
        return $this->belongsTo(SabbathSchoolSegmentContent::class, 'segment_content_id');
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return SabbathSchoolAnswerQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new SabbathSchoolAnswerQueryBuilder($query);
    }
}
