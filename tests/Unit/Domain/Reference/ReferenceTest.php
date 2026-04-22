<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reference;

use App\Domain\Reference\Exceptions\InvalidReferenceException;
use App\Domain\Reference\Reference;
use PHPUnit\Framework\TestCase;

final class ReferenceTest extends TestCase
{
    public function test_whole_chapter(): void
    {
        $ref = new Reference('GEN', 1, [], 'VDC');

        $this->assertTrue($ref->isWholeChapter());
        $this->assertFalse($ref->isSingleVerse());
        $this->assertFalse($ref->isRange());
        $this->assertSame(0, $ref->getVerse());
    }

    public function test_single_verse(): void
    {
        $ref = new Reference('GEN', 1, [5], 'VDC');

        $this->assertFalse($ref->isWholeChapter());
        $this->assertTrue($ref->isSingleVerse());
        $this->assertFalse($ref->isRange());
        $this->assertSame(5, $ref->getVerse());
    }

    public function test_range(): void
    {
        $ref = new Reference('GEN', 1, [1, 2, 3], 'VDC');

        $this->assertFalse($ref->isWholeChapter());
        $this->assertFalse($ref->isSingleVerse());
        $this->assertTrue($ref->isRange());
        $this->assertSame(1, $ref->getVerse());
    }

    public function test_constructor_rejects_non_ascending_verses(): void
    {
        $this->expectException(InvalidReferenceException::class);

        new Reference('GEN', 1, [3, 2, 1], 'VDC');
    }

    public function test_constructor_rejects_duplicate_verses(): void
    {
        $this->expectException(InvalidReferenceException::class);

        new Reference('GEN', 1, [1, 2, 2], 'VDC');
    }

    public function test_constructor_rejects_non_positive_verses(): void
    {
        $this->expectException(InvalidReferenceException::class);

        new Reference('GEN', 1, [0, 1], 'VDC');
    }

    public function test_version_is_optional(): void
    {
        $ref = new Reference('GEN', 1, [1]);

        $this->assertNull($ref->version);
    }
}
