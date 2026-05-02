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
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
