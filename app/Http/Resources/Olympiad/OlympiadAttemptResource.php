<?php

declare(strict_types=1);

namespace App\Http\Resources\Olympiad;

use App\Domain\Olympiad\Models\OlympiadAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OlympiadAttempt
 */
final class OlympiadAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'book' => $this->book,
            'chapters_label' => $this->chapters_label,
            'language' => $this->language->value,
            'score' => $this->score,
            'total' => $this->total,
            'started_at' => $this->started_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
