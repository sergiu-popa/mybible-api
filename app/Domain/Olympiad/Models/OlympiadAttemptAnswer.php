<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Models;

use Database\Factories\OlympiadAttemptAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $attempt_id
 * @property int $olympiad_question_id
 * @property ?int $selected_answer_id
 * @property bool $is_correct
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read OlympiadAttempt $attempt
 * @property-read OlympiadQuestion $question
 * @property-read ?OlympiadAnswer $selectedAnswer
 */
#[UseFactory(OlympiadAttemptAnswerFactory::class)]
final class OlympiadAttemptAnswer extends Model
{
    /** @use HasFactory<OlympiadAttemptAnswerFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $primaryKey = 'attempt_id';

    /** @var list<string> */
    protected $primaryKeyComposite = ['attempt_id', 'olympiad_question_id'];

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<OlympiadAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(OlympiadAttempt::class, 'attempt_id');
    }

    /**
     * @return BelongsTo<OlympiadQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(OlympiadQuestion::class, 'olympiad_question_id');
    }

    /**
     * @return BelongsTo<OlympiadAnswer, $this>
     */
    public function selectedAnswer(): BelongsTo
    {
        return $this->belongsTo(OlympiadAnswer::class, 'selected_answer_id');
    }
}
