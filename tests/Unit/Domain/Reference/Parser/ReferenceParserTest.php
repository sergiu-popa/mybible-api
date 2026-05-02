<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reference\Parser;

use App\Domain\Reference\Exceptions\InvalidReferenceException;
use App\Domain\Reference\Parser\ReferenceParser;
use App\Domain\Reference\Reference;
use App\Domain\Reference\VerseRange;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $this->assertInstanceOf(Reference::class, $ref);
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

    public function test_parse_one_cross_chapter_range(): void
    {
        $parsed = $this->parser->parseOne('MAT.19:27-20:16.VDC');

        $this->assertInstanceOf(VerseRange::class, $parsed);
        $this->assertEquals(
            new VerseRange('MAT', 19, 27, 20, 16, 'VDC'),
            $parsed,
        );
    }

    public function test_parse_one_cross_chapter_without_version(): void
    {
        $parsed = $this->parser->parseOne('MAT.19:27-20:16.');

        $this->assertEquals(new VerseRange('MAT', 19, 27, 20, 16, null), $parsed);
    }

    public function test_parse_one_cross_chapter_spans_more_than_two_chapters(): void
    {
        $parsed = $this->parser->parseOne('JHN.5:1-7:30.VDC');

        $this->assertEquals(new VerseRange('JHN', 5, 1, 7, 30, 'VDC'), $parsed);
    }

    public function test_parse_dispatch_cross_chapter_returns_single_verse_range(): void
    {
        $parsed = $this->parser->parse('MAT.19:27-20:16.VDC');

        $this->assertCount(1, $parsed);
        $this->assertInstanceOf(VerseRange::class, $parsed[0]);
    }

    public function test_parse_dispatch_in_chapter_range_still_returns_reference(): void
    {
        $parsed = $this->parser->parse('ROM.8:28-30.VDC');

        $this->assertCount(1, $parsed);
        $this->assertEquals(new Reference('ROM', 8, [28, 29, 30], 'VDC'), $parsed[0]);
    }

    public function test_parse_dispatch_chapter_only_returns_reference(): void
    {
        $parsed = $this->parser->parse('1CO.13.VDC');

        $this->assertCount(1, $parsed);
        $this->assertEquals(new Reference('1CO', 13, [], 'VDC'), $parsed[0]);
    }

    public function test_parse_dispatch_chapter_range_returns_multiple_references(): void
    {
        $parsed = $this->parser->parse('1CO.13-14.VDC');

        $this->assertCount(2, $parsed);
        $this->assertEquals(new Reference('1CO', 13, [], 'VDC'), $parsed[0]);
        $this->assertEquals(new Reference('1CO', 14, [], 'VDC'), $parsed[1]);
    }

    public function test_parse_dispatch_multi_with_cross_chapter_segment(): void
    {
        $parsed = $this->parser->parse('MAT.5:3;19:27-20:16.VDC');

        $this->assertCount(2, $parsed);
        $this->assertEquals(new Reference('MAT', 5, [3], 'VDC'), $parsed[0]);
        $this->assertEquals(new VerseRange('MAT', 19, 27, 20, 16, 'VDC'), $parsed[1]);
    }

    public function test_parse_one_cross_chapter_minimum_one_verse_each_side(): void
    {
        $parsed = $this->parser->parseOne('GEN.1:1-2:1.VDC');

        $this->assertEquals(new VerseRange('GEN', 1, 1, 2, 1, 'VDC'), $parsed);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidCrossChapterReferences(): array
    {
        return [
            'end before start chapter' => ['MAT.20:1-19:5.VDC'],
            'same chapter end <= start verse' => ['MAT.19:5-19:5.VDC'],
            'zero verse on right' => ['MAT.19:1-20:0.VDC'],
            'zero verse on left' => ['MAT.19:0-20:5.VDC'],
            'malformed double colon' => ['MAT.19::27-20:16.VDC'],
            'trailing dash' => ['MAT.19:27-.VDC'],
            'non-numeric chapter on right' => ['MAT.19:27-AB:16.VDC'],
            'non-numeric verse on right' => ['MAT.19:27-20:AB.VDC'],
            'unknown book in cross-chapter' => ['XYZ.1:1-2:1.VDC'],
            'right chapter out of range' => ['MAT.27:1-99:1.VDC'],
        ];
    }

    #[DataProvider('invalidCrossChapterReferences')]
    public function test_parse_one_throws_on_invalid_cross_chapter(string $query): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->parser->parseOne($query);
    }
}
