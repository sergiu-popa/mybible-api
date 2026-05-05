<?php

declare(strict_types=1);

namespace App\Http\Resources\Commentary;

use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Http\Request;

/**
 * @mixin CommentaryText
 */
final class AdminCommentaryTextResource extends CommentaryTextResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'commentary_id' => $this->commentary_id,
            ...parent::toArray($request),
            'original' => $this->original,
            'plain' => $this->plain,
            'with_references' => $this->with_references,
            'errors_reported' => (int) $this->errors_reported,
            'ai_corrected_at' => $this->ai_corrected_at?->toIso8601String(),
            'ai_corrected_prompt_version' => $this->ai_corrected_prompt_version,
            'ai_referenced_at' => $this->ai_referenced_at?->toIso8601String(),
            'ai_referenced_prompt_version' => $this->ai_referenced_prompt_version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
