<?php

declare(strict_types=1);

namespace App\Http\Resources\Commentary;

use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CommentaryText
 */
class CommentaryTextResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'book' => $this->book,
            'chapter' => $this->chapter,
            'position' => $this->position,
            'verse_from' => $this->verse_from,
            'verse_to' => $this->verse_to,
            'verse_label' => $this->verse_label,
            'content' => $this->resolvedContent(),
            'errors_reported' => (int) $this->errors_reported,
        ];
    }
}
