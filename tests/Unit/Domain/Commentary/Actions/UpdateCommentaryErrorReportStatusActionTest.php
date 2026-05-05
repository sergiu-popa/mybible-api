<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Commentary\Actions;

use App\Domain\Commentary\Actions\UpdateCommentaryErrorReportStatusAction;
use App\Domain\Commentary\DataTransferObjects\UpdateCommentaryErrorReportData;
use App\Domain\Commentary\Enums\CommentaryErrorReportStatus;
use App\Domain\Commentary\Models\CommentaryErrorReport;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UpdateCommentaryErrorReportStatusActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_to_fixed_decrements(): void
    {
        $text = CommentaryText::factory()->create(['errors_reported' => 1]);
        $report = CommentaryErrorReport::factory()->create([
            'commentary_text_id' => $text->id,
            'status' => CommentaryErrorReportStatus::Pending,
        ]);

        (new UpdateCommentaryErrorReportStatusAction)->execute(
            $report,
            new UpdateCommentaryErrorReportData(CommentaryErrorReportStatus::Fixed, null),
        );

        self::assertSame(0, (int) $text->refresh()->errors_reported);
    }

    public function test_pending_to_reviewed_keeps_counter(): void
    {
        $text = CommentaryText::factory()->create(['errors_reported' => 1]);
        $report = CommentaryErrorReport::factory()->create([
            'commentary_text_id' => $text->id,
            'status' => CommentaryErrorReportStatus::Pending,
        ]);

        (new UpdateCommentaryErrorReportStatusAction)->execute(
            $report,
            new UpdateCommentaryErrorReportData(CommentaryErrorReportStatus::Reviewed, null),
        );

        self::assertSame(1, (int) $text->refresh()->errors_reported);
    }

    public function test_fixed_to_pending_increments_back(): void
    {
        $text = CommentaryText::factory()->create(['errors_reported' => 0]);
        $report = CommentaryErrorReport::factory()->fixed()->create([
            'commentary_text_id' => $text->id,
        ]);

        (new UpdateCommentaryErrorReportStatusAction)->execute(
            $report,
            new UpdateCommentaryErrorReportData(CommentaryErrorReportStatus::Pending, null),
        );

        self::assertSame(1, (int) $text->refresh()->errors_reported);
    }

    public function test_counter_floors_at_zero(): void
    {
        $text = CommentaryText::factory()->create(['errors_reported' => 0]);
        $report = CommentaryErrorReport::factory()->create([
            'commentary_text_id' => $text->id,
            'status' => CommentaryErrorReportStatus::Pending,
        ]);

        (new UpdateCommentaryErrorReportStatusAction)->execute(
            $report,
            new UpdateCommentaryErrorReportData(CommentaryErrorReportStatus::Dismissed, null),
        );

        self::assertSame(0, (int) $text->refresh()->errors_reported);
    }
}
