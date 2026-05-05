<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Commentary\Enums;

use App\Domain\Commentary\Enums\CommentaryErrorReportStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CommentaryErrorReportStatusTest extends TestCase
{
    public function test_open_states(): void
    {
        self::assertTrue(CommentaryErrorReportStatus::Pending->isOpen());
        self::assertTrue(CommentaryErrorReportStatus::Reviewed->isOpen());
        self::assertFalse(CommentaryErrorReportStatus::Fixed->isOpen());
        self::assertFalse(CommentaryErrorReportStatus::Dismissed->isOpen());
    }

    #[DataProvider('counterDeltaCases')]
    public function test_counter_delta_matrix(
        CommentaryErrorReportStatus $from,
        CommentaryErrorReportStatus $to,
        int $expected,
    ): void {
        self::assertSame($expected, $from->counterDelta($to));
    }

    /**
     * @return iterable<string, array{0: CommentaryErrorReportStatus, 1: CommentaryErrorReportStatus, 2: int}>
     */
    public static function counterDeltaCases(): iterable
    {
        yield 'pending → reviewed (still open)' => [
            CommentaryErrorReportStatus::Pending,
            CommentaryErrorReportStatus::Reviewed,
            0,
        ];
        yield 'reviewed → pending (still open)' => [
            CommentaryErrorReportStatus::Reviewed,
            CommentaryErrorReportStatus::Pending,
            0,
        ];
        yield 'pending → fixed (closing)' => [
            CommentaryErrorReportStatus::Pending,
            CommentaryErrorReportStatus::Fixed,
            -1,
        ];
        yield 'pending → dismissed (closing)' => [
            CommentaryErrorReportStatus::Pending,
            CommentaryErrorReportStatus::Dismissed,
            -1,
        ];
        yield 'reviewed → fixed (closing)' => [
            CommentaryErrorReportStatus::Reviewed,
            CommentaryErrorReportStatus::Fixed,
            -1,
        ];
        yield 'fixed → pending (re-opening)' => [
            CommentaryErrorReportStatus::Fixed,
            CommentaryErrorReportStatus::Pending,
            1,
        ];
        yield 'dismissed → reviewed (re-opening)' => [
            CommentaryErrorReportStatus::Dismissed,
            CommentaryErrorReportStatus::Reviewed,
            1,
        ];
        yield 'fixed → dismissed (closed → closed)' => [
            CommentaryErrorReportStatus::Fixed,
            CommentaryErrorReportStatus::Dismissed,
            0,
        ];
        yield 'pending → pending (no-op)' => [
            CommentaryErrorReportStatus::Pending,
            CommentaryErrorReportStatus::Pending,
            0,
        ];
    }
}
