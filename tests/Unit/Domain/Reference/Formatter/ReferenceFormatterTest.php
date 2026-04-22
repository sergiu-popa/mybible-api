<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reference\Formatter;

use App\Domain\Reference\Exceptions\InvalidReferenceException;
use App\Domain\Reference\Formatter\ReferenceFormatter;
use App\Domain\Reference\Reference;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReferenceFormatterTest extends TestCase
{
    private ReferenceFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ReferenceFormatter;
    }

    /**
     * @param  array{reference: Reference, expected: string}  $fixture
     */
    #[DataProvider('canonicalProvider')]
    public function test_to_canonical(string $name, array $fixture): void
    {
        $this->assertSame($fixture['expected'], $this->formatter->toCanonical($fixture['reference']));
    }

    public static function canonicalProvider(): iterable
    {
        foreach (require __DIR__ . '/../fixtures/canonical.php' as $name => $fixture) {
            yield $name => [$name, $fixture];
        }
    }

    public function test_to_canonical_rejects_null_version(): void
    {
        $this->expectException(InvalidReferenceException::class);

        $this->formatter->toCanonical(new Reference('GEN', 1, [1]));
    }

    /**
     * @param  array{reference: Reference, expected: string}  $fixture
     */
    #[DataProvider('humanReadableRoProvider')]
    public function test_human_readable_romanian(array $fixture): void
    {
        $this->assertSame($fixture['expected'], $this->formatter->toHumanReadable($fixture['reference'], 'ro'));
    }

    public static function humanReadableRoProvider(): iterable
    {
        foreach (require __DIR__ . '/../fixtures/human-readable.ro.php' as $i => $fixture) {
            yield "ro-$i" => [$fixture];
        }
    }

    /**
     * @param  array{reference: Reference, expected: string}  $fixture
     */
    #[DataProvider('humanReadableEnProvider')]
    public function test_human_readable_english(array $fixture): void
    {
        $this->assertSame($fixture['expected'], $this->formatter->toHumanReadable($fixture['reference'], 'en'));
    }

    public static function humanReadableEnProvider(): iterable
    {
        foreach (require __DIR__ . '/../fixtures/human-readable.en.php' as $i => $fixture) {
            yield "en-$i" => [$fixture];
        }
    }

    /**
     * @param  array{reference: Reference, expected: string}  $fixture
     */
    #[DataProvider('humanReadableHuProvider')]
    public function test_human_readable_hungarian(array $fixture): void
    {
        $this->assertSame($fixture['expected'], $this->formatter->toHumanReadable($fixture['reference'], 'hu'));
    }

    public static function humanReadableHuProvider(): iterable
    {
        foreach (require __DIR__ . '/../fixtures/human-readable.hu.php' as $i => $fixture) {
            yield "hu-$i" => [$fixture];
        }
    }

    public function test_unsupported_language_falls_back_to_english(): void
    {
        $ref = new Reference('GEN', 1, [1], 'KJV');

        $this->assertSame(
            'Genesis 1:1',
            $this->formatter->toHumanReadable($ref, 'fr'),
        );
    }
}
