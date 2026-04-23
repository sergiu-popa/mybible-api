<?php

declare(strict_types=1);

namespace App\Http\Resources\Olympiad;

use App\Domain\Olympiad\Models\OlympiadQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OlympiadQuestion
 */
final class OlympiadQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'explanation' => $this->explanation,
            'answers' => OlympiadAnswerResource::collection($this->answers),
        ];
    }
}
