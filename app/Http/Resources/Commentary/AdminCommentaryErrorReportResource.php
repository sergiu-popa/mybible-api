<?php

declare(strict_types=1);

namespace App\Http\Resources\Commentary;

use App\Domain\Commentary\Models\CommentaryErrorReport;
use Illuminate\Http\Request;

/**
 * Admin-shape error report — adds reviewer info, full description, and
 * the device/user attribution columns that triage staff need.
 *
 * @mixin CommentaryErrorReport
 */
final class AdminCommentaryErrorReportResource extends CommentaryErrorReportResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'user_id' => $this->user_id,
            'device_id' => $this->device_id,
            'reviewed_by_user_id' => $this->reviewed_by_user_id,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
