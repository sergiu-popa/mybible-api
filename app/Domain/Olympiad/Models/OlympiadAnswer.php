<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Models;

use Database\Factories\OlympiadAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $olympiad_question_id
 * @property string $text
 * @property bool $is_correct
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read OlympiadQuestion $question
 */
#[UseFactory(OlympiadAnswerFactory::class)]
final class OlympiadAnswer extends Model
{
    /** @use HasFactory<OlympiadAnswerFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<OlympiadQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(OlympiadQuestion::class, 'olympiad_question_id');
    }
}
