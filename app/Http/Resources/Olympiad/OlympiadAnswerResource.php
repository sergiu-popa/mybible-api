<?php

declare(strict_types=1);

namespace App\Http\Resources\Olympiad;

use App\Domain\Olympiad\Models\OlympiadAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OlympiadAnswer
 */
final class OlympiadAnswerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'text' => $this->text,
            'is_correct' => $this->is_correct,
        ];
    }
}
