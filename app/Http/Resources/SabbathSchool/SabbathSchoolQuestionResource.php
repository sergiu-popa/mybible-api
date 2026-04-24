<?php

declare(strict_types=1);

namespace App\Http\Resources\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SabbathSchoolQuestion
 */
final class SabbathSchoolQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prompt' => $this->prompt,
        ];
    }
}
