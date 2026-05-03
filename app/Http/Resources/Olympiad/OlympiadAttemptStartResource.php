<?php

declare(strict_types=1);

namespace App\Http\Resources\Olympiad;

use App\Domain\Olympiad\Models\OlympiadAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OlympiadAttemptStartResource extends JsonResource
{
    /**
     * @param  list<string>  $questionUuids
     */
    public function __construct(
        OlympiadAttempt $attempt,
        private readonly array $questionUuids,
    ) {
        parent::__construct($attempt);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OlympiadAttempt $attempt */
        $attempt = $this->resource;

        return [
            'id' => $attempt->id,
            'book' => $attempt->book,
            'chapters_label' => $attempt->chapters_label,
            'language' => $attempt->language->value,
            'score' => $attempt->score,
            'total' => $attempt->total,
            'started_at' => $attempt->started_at->toIso8601String(),
            'completed_at' => $attempt->completed_at?->toIso8601String(),
            'question_uuids' => $this->questionUuids,
        ];
    }
}
