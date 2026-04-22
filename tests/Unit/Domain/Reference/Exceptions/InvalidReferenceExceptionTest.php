<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reference\Exceptions;

use App\Domain\Reference\Exceptions\InvalidReferenceException;
use PHPUnit\Framework\TestCase;

final class InvalidReferenceExceptionTest extends TestCase
{
    public function test_unparseable_factory_sets_input_and_reason(): void
    {
        $e = InvalidReferenceException::unparseable('FOO', 'expected three parts');

        $this->assertSame('FOO', $e->input());
        $this->assertSame('expected three parts', $e->reason());
        $this->assertStringContainsString('FOO', $e->getMessage());
        $this->assertStringContainsString('expected three parts', $e->getMessage());
    }

    public function test_unknown_book_factory_sets_input_and_reason(): void
    {
        $e = InvalidReferenceException::unknownBook('XYZ.1.VDC', 'XYZ');

        $this->assertSame('XYZ.1.VDC', $e->input());
        $this->assertStringContainsString('unknown book', $e->reason());
        $this->assertStringContainsString('XYZ', $e->reason());
    }

    public function test_invalid_verses_factory_sets_context(): void
    {
        $e = InvalidReferenceException::invalidVerses('GEN', 1, 'verses must be ascending and unique');

        $this->assertSame('GEN.1', $e->input());
        $this->assertSame('verses must be ascending and unique', $e->reason());
        $this->assertStringContainsString('GEN.1', $e->getMessage());
    }

    public function test_chapter_out_of_range_factory_sets_context(): void
    {
        $e = InvalidReferenceException::chapterOutOfRange('GEN.99.VDC', 'GEN', 99, 50);

        $this->assertSame('GEN.99.VDC', $e->input());
        $this->assertStringContainsString('chapter 99', $e->reason());
        $this->assertStringContainsString('GEN', $e->reason());
        $this->assertStringContainsString('max 50', $e->reason());
    }
}
