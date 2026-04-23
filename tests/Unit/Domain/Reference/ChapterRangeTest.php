<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reference;

use App\Domain\Reference\ChapterRange;
use App\Domain\Reference\Exceptions\InvalidReferenceException;
use PHPUnit\Framework\TestCase;

final class ChapterRangeTest extends TestCase
{
    public function test_from_segment_parses_single_chapter(): void
    {
        $range = ChapterRange::fromSegment('5');

        $this->assertSame(5, $range->from);
        $this->assertSame(5, $range->to);
        $this->assertTrue($range->isSingleChapter());
        $this->assertSame('5', $range->toCanonicalSegment());
    }

    public function test_from_segment_parses_range(): void
    {
        $range = ChapterRange::fromSegment('1-3');

        $this->assertSame(1, $range->from);
        $this->assertSame(3, $range->to);
        $this->assertFalse($range->isSingleChapter());
        $this->assertSame('1-3', $range->toCanonicalSegment());
    }

    public function test_throws_for_zero(): void
    {
        $this->expectException(InvalidReferenceException::class);

        ChapterRange::fromSegment('0');
    }

    public function test_throws_for_inverted_range(): void
    {
        $this->expectException(InvalidReferenceException::class);

        ChapterRange::fromSegment('3-1');
    }

    public function test_throws_for_empty_segment(): void
    {
        $this->expectException(InvalidReferenceException::class);

        ChapterRange::fromSegment('');
    }

    public function test_throws_for_non_numeric(): void
    {
        $this->expectException(InvalidReferenceException::class);

        ChapterRange::fromSegment('a-b');
    }

    public function test_throws_for_open_ended_range(): void
    {
        $this->expectException(InvalidReferenceException::class);

        ChapterRange::fromSegment('1-');
    }

    public function test_throws_for_non_digit_single(): void
    {
        $this->expectException(InvalidReferenceException::class);

        ChapterRange::fromSegment('abc');
    }
}
