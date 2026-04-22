<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reference\Data;

use App\Domain\Reference\Data\BibleBookCatalog;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BibleBookCatalogTest extends TestCase
{
    public function test_it_has_exactly_66_books(): void
    {
        $this->assertCount(66, BibleBookCatalog::BOOKS);
    }

    public function test_every_entry_is_non_empty(): void
    {
        foreach (BibleBookCatalog::BOOKS as $abbrev => $maxChapter) {
            $this->assertNotEmpty($abbrev);
            $this->assertGreaterThan(0, $maxChapter);
        }
    }

    public function test_has_book_returns_true_for_known_book(): void
    {
        $this->assertTrue(BibleBookCatalog::hasBook('GEN'));
        $this->assertTrue(BibleBookCatalog::hasBook('REV'));
    }

    public function test_has_book_returns_false_for_unknown_book(): void
    {
        $this->assertFalse(BibleBookCatalog::hasBook('XYZ'));
        $this->assertFalse(BibleBookCatalog::hasBook(''));
    }

    public function test_max_chapter_returns_known_value(): void
    {
        $this->assertSame(50, BibleBookCatalog::maxChapter('GEN'));
        $this->assertSame(150, BibleBookCatalog::maxChapter('PSA'));
        $this->assertSame(22, BibleBookCatalog::maxChapter('REV'));
    }

    public function test_max_chapter_throws_on_unknown_book(): void
    {
        $this->expectException(RuntimeException::class);

        BibleBookCatalog::maxChapter('XYZ');
    }
}
