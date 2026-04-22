<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reference\Formatter\Languages;

use App\Domain\Reference\Data\BibleBookCatalog;
use App\Domain\Reference\Exceptions\InvalidReferenceException;
use App\Domain\Reference\Formatter\Languages\HungarianFormatter;
use PHPUnit\Framework\TestCase;

final class HungarianFormatterTest extends TestCase
{
    private HungarianFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new HungarianFormatter;
    }

    public function test_default_version_is_kar(): void
    {
        $this->assertSame('KAR', $this->formatter->defaultVersion());
    }

    public function test_round_trip_for_every_book(): void
    {
        foreach (array_keys(BibleBookCatalog::BOOKS) as $abbrev) {
            $localized = $this->formatter->bookName($abbrev);

            $this->assertSame(
                $abbrev,
                $this->formatter->abbreviation($localized),
                sprintf('Round-trip failed for %s ("%s")', $abbrev, $localized),
            );
        }
    }

    public function test_both_short_and_long_genesis_aliases_resolve(): void
    {
        $this->assertSame('GEN', $this->formatter->abbreviation('1Móz'));
        $this->assertSame('GEN', $this->formatter->abbreviation('1Mózes'));
    }

    public function test_unknown_abbreviation_throws(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->formatter->bookName('XYZ');
    }

    public function test_unknown_book_name_throws(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->formatter->abbreviation('Bogus');
    }
}
