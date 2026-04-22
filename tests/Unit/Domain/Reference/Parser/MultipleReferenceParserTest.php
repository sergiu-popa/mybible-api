<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reference\Parser;

use App\Domain\Reference\Exceptions\InvalidReferenceException;
use App\Domain\Reference\Parser\MultipleReferenceParser;
use PHPUnit\Framework\TestCase;

final class MultipleReferenceParserTest extends TestCase
{
    private MultipleReferenceParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MultipleReferenceParser;
    }

    public function test_expands_mixed_partials_and_whole_chapter(): void
    {
        $this->assertSame(
            ['GEN.1:1.VDC', 'GEN.2.VDC', 'GEN.3:5-7.VDC'],
            $this->parser->expand('GEN.1:1;2;3:5-7.VDC'),
        );
    }

    public function test_expands_separate_chapters(): void
    {
        $this->assertSame(
            ['GEN.1.VDC', 'GEN.2.VDC', 'GEN.5.VDC'],
            $this->parser->expand('GEN.1;2;5.VDC'),
        );
    }

    public function test_throws_when_no_collection_delimiter(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->parser->expand('GEN.1:1.VDC');
    }

    public function test_throws_on_empty_sub_reference(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->parser->expand('GEN.1;;2.VDC');
    }

    public function test_throws_when_segments_missing(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->parser->expand('GEN.1;2');
    }
}
