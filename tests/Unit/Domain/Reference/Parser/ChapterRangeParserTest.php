<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reference\Parser;

use App\Domain\Reference\Exceptions\InvalidReferenceException;
use App\Domain\Reference\Parser\ChapterRangeParser;
use PHPUnit\Framework\TestCase;

final class ChapterRangeParserTest extends TestCase
{
    private ChapterRangeParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ChapterRangeParser;
    }

    public function test_expands_chapter_range(): void
    {
        $this->assertSame(
            ['GEN.1.VDC', 'GEN.2.VDC', 'GEN.3.VDC'],
            $this->parser->expand('GEN.1-3.VDC'),
        );
    }

    public function test_expands_single_chapter_range(): void
    {
        $this->assertSame(
            ['GEN.5.VDC'],
            $this->parser->expand('GEN.5-5.VDC'),
        );
    }

    public function test_throws_when_segments_missing(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->parser->expand('GEN.1-3');
    }

    public function test_throws_when_no_dash(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->parser->expand('GEN.1.VDC');
    }

    public function test_throws_when_bounds_inverted(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->parser->expand('GEN.5-3.VDC');
    }

    public function test_throws_when_bound_empty(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->parser->expand('GEN.1-.VDC');
    }
}
