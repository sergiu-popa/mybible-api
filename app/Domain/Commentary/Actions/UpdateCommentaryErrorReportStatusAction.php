<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Actions;

use App\Domain\Commentary\DataTransferObjects\UpdateCommentaryErrorReportData;
use App\Domain\Commentary\Enums\CommentaryErrorReportStatus;
use App\Domain\Commentary\Models\CommentaryErrorReport;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a {@see CommentaryErrorReport}'s status, sets the reviewer
 * + timestamp, and adjusts the denormalised `errors_reported` counter
 * on the parent `CommentaryText` per the open/closed transition.
 *
 * The counter delta is computed by
 * {@see CommentaryErrorReportStatus::counterDelta()}
 * and clamped to a 0 floor.
 */
final class UpdateCommentaryErrorReportStatusAction
{
    public function execute(
        CommentaryErrorReport $report,
        UpdateCommentaryErrorReportData $data,
    ): CommentaryErrorReport {
        return DB::transaction(function () use ($report, $data): CommentaryErrorReport {
            // FK is `ON DELETE CASCADE`, so a parent text always exists
            // while the report does — `findOrFail` surfaces an
            // invariant break loudly instead of silently skipping the
            // counter update.
            /** @var CommentaryText $text */
            $text = CommentaryText::query()
                ->lockForUpdate()
                ->findOrFail($report->commentary_text_id);

            $report->refresh();
            $previous = $report->status;

            $delta = $previous->counterDelta($data->status);

            if ($delta !== 0) {
                $next = (int) $text->errors_reported + $delta;
                $text->forceFill([
                    'errors_reported' => max(0, $next),
                ])->save();
            }

            $report->forceFill([
                'status' => $data->status,
                'reviewed_by_user_id' => $data->reviewedByUserId,
                'reviewed_at' => Carbon::now(),
            ])->save();

            return $report->refresh();
        });
    }
}
