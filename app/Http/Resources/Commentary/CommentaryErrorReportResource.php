<?php

declare(strict_types=1);

namespace App\Http\Resources\Commentary;

use App\Domain\Commentary\Models\CommentaryErrorReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public-shape error report — what an end user (or device) sees back
 * after submitting a report. Reviewer info and full description are
 * intentionally excluded; admins see the richer admin shape.
 *
 * @mixin CommentaryErrorReport
 */
class CommentaryErrorReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'commentary_text_id' => $this->commentary_text_id,
            'book' => $this->book,
            'chapter' => $this->chapter,
            'verse' => $this->verse,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
