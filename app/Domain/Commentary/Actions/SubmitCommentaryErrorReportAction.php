<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Actions;

use App\Domain\Commentary\DataTransferObjects\SubmitCommentaryErrorReportData;
use App\Domain\Commentary\Enums\CommentaryErrorReportStatus;
use App\Domain\Commentary\Models\CommentaryErrorReport;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records a user-reported error against a `CommentaryText` and bumps
 * the denormalised `errors_reported` counter on the parent row inside
 * a row-level lock so concurrent submissions don't desynchronise.
 */
final class SubmitCommentaryErrorReportAction
{
    public function execute(SubmitCommentaryErrorReportData $data): CommentaryErrorReport
    {
        return DB::transaction(function () use ($data): CommentaryErrorReport {
            /** @var CommentaryText|null $text */
            $text = CommentaryText::query()
                ->lockForUpdate()
                ->find($data->commentaryTextId);

            if (! $text instanceof CommentaryText) {
                throw new RuntimeException(sprintf(
                    'CommentaryText #%d not found.',
                    $data->commentaryTextId,
                ));
            }

            /** @var CommentaryErrorReport $report */
            $report = $text->errorReports()->create([
                'user_id' => $data->userId,
                'device_id' => $data->deviceId,
                'book' => $text->book,
                'chapter' => $text->chapter,
                'verse' => $data->verse,
                'description' => $data->description,
                'status' => CommentaryErrorReportStatus::Pending,
            ]);

            $text->forceFill([
                'errors_reported' => (int) $text->errors_reported + 1,
            ])->save();

            return $report;
        });
    }
}
