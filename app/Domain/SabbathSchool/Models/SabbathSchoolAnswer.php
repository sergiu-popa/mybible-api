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
 * @property int $sabbath_school_question_id
 * @property string $content
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read SabbathSchoolQuestion $question
 */
#[UseFactory(SabbathSchoolAnswerFactory::class)]
final class SabbathSchoolAnswer extends Model
{
    /** @use HasFactory<SabbathSchoolAnswerFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SabbathSchoolQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(SabbathSchoolQuestion::class, 'sabbath_school_question_id');
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
