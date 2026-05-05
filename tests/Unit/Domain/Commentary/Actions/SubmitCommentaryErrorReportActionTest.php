<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Commentary\Actions;

use App\Domain\Commentary\Actions\SubmitCommentaryErrorReportAction;
use App\Domain\Commentary\DataTransferObjects\SubmitCommentaryErrorReportData;
use App\Domain\Commentary\Enums\CommentaryErrorReportStatus;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubmitCommentaryErrorReportActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_report_and_increments_counter(): void
    {
        $text = CommentaryText::factory()->create(['errors_reported' => 0]);

        $report = (new SubmitCommentaryErrorReportAction)->execute(
            new SubmitCommentaryErrorReportData(
                commentaryTextId: (int) $text->id,
                description: 'Typo in verse 3.',
                verse: 3,
                deviceId: 'd1',
            ),
        );

        $text->refresh();

        self::assertSame(1, (int) $text->errors_reported);
        self::assertSame(CommentaryErrorReportStatus::Pending, $report->status);
        self::assertSame('Typo in verse 3.', $report->description);
    }

    public function test_two_reports_double_counter(): void
    {
        $text = CommentaryText::factory()->create(['errors_reported' => 0]);

        $action = new SubmitCommentaryErrorReportAction;
        $action->execute(new SubmitCommentaryErrorReportData(
            commentaryTextId: (int) $text->id,
            description: 'first',
        ));
        $action->execute(new SubmitCommentaryErrorReportData(
            commentaryTextId: (int) $text->id,
            description: 'second',
        ));

        self::assertSame(2, (int) $text->refresh()->errors_reported);
    }
}
