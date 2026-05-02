<?php

declare(strict_types=1);

namespace App\Http\Resources\Commentary;

use App\Domain\Commentary\Models\Commentary;
use Illuminate\Http\Request;

/**
 * @mixin Commentary
 */
final class AdminCommentaryResource extends CommentaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            ...parent::toArray($request),
            'language' => $this->language,
            'is_published' => $this->is_published,
            'source_commentary_id' => $this->source_commentary_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
