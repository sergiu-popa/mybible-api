<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reference\Parser;

use App\Domain\Reference\Exceptions\InvalidReferenceException;
use App\Domain\Reference\Parser\ReferenceParser;
use App\Domain\Reference\Reference;
use PHPUnit\Framework\TestCase;

final class ReferenceParserTest extends TestCase
{
    private ReferenceParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ReferenceParser;
    }

    public function test_parse_one_whole_chapter(): void
    {
        $ref = $this->parser->parseOne('GEN.1.VDC');

        $this->assertEquals(new Reference('GEN', 1, [], 'VDC'), $ref);
        $this->assertTrue($ref->isWholeChapter());
    }

    public function test_parse_one_single_verse(): void
    {
        $this->assertEquals(
            new Reference('GEN', 1, [1], 'VDC'),
            $this->parser->parseOne('GEN.1:1.VDC'),
        );
    }

    public function test_parse_one_verse_range(): void
    {
        $this->assertEquals(
            new Reference('GEN', 1, [1, 2, 3], 'VDC'),
            $this->parser->parseOne('GEN.1:1-3.VDC'),
        );
    }

    public function test_parse_one_comma_list(): void
    {
        $this->assertEquals(
            new Reference('GEN', 1, [1, 3, 5], 'VDC'),
            $this->parser->parseOne('GEN.1:1,3,5.VDC'),
        );
    }

    public function test_parse_one_mixed(): void
    {
        $this->assertEquals(
            new Reference('GEN', 1, [1, 2, 3, 5, 7, 8, 9], 'VDC'),
            $this->parser->parseOne('GEN.1:1-3,5,7-9.VDC'),
        );
    }

    public function test_parse_one_dedups_overlap(): void
    {
        $this->assertEquals(
            new Reference('GEN', 1, [1, 2, 3, 4, 5, 6, 7], 'VDC'),
            $this->parser->parseOne('GEN.1:1-3,5,2-7.VDC'),
        );
    }

    public function test_parse_one_throws_on_unknown_book(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->parser->parseOne('XYZ.1.VDC');
    }

    public function test_parse_one_throws_on_chapter_out_of_range(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->parser->parseOne('GEN.99.VDC');
    }

    public function test_parse_one_throws_on_zero_chapter(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->parser->parseOne('GEN.0.VDC');
    }

    public function test_parse_one_throws_on_missing_segments(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->parser->parseOne('GEN.1');
    }

    public function test_parse_one_throws_on_extra_segments(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->parser->parseOne('GEN.1.VDC.EXTRA');
    }

    public function test_parse_one_rejects_multi_reference_input(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->parser->parseOne('GEN.1:1;2.VDC');
    }

    public function test_parse_one_rejects_chapter_range_input(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->parser->parseOne('GEN.1-3.VDC');
    }

    public function test_parse_dispatch_single_reference(): void
    {
        $refs = $this->parser->parse('GEN.1:1.VDC');

        $this->assertCount(1, $refs);
        $this->assertEquals(new Reference('GEN', 1, [1], 'VDC'), $refs[0]);
    }

    public function test_parse_dispatch_chapter_range(): void
    {
        $refs = $this->parser->parse('GEN.1-3.VDC');

        $this->assertCount(3, $refs);
        $this->assertEquals(new Reference('GEN', 1, [], 'VDC'), $refs[0]);
        $this->assertEquals(new Reference('GEN', 2, [], 'VDC'), $refs[1]);
        $this->assertEquals(new Reference('GEN', 3, [], 'VDC'), $refs[2]);
    }

    public function test_parse_dispatch_multiple(): void
    {
        $refs = $this->parser->parse('GEN.1:1;2;3:5-7.VDC');

        $this->assertCount(3, $refs);
        $this->assertEquals(new Reference('GEN', 1, [1], 'VDC'), $refs[0]);
        $this->assertEquals(new Reference('GEN', 2, [], 'VDC'), $refs[1]);
        $this->assertEquals(new Reference('GEN', 3, [5, 6, 7], 'VDC'), $refs[2]);
    }

    public function test_parse_bubbles_up_invalid_reference_exception(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->parser->parse('GEN.1:1;XYZ.VDC');
    }
}
