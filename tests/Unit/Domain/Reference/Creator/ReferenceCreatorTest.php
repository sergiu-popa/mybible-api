<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reference\Creator;

use App\Domain\Reference\Creator\ReferenceCreator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReferenceCreatorTest extends TestCase
{
    private ReferenceCreator $creator;

    protected function setUp(): void
    {
        $this->creator = new ReferenceCreator;
    }

    /**
     * @param  array{input: string, expected: string}  $fixture
     */
    #[DataProvider('roFixtures')]
    public function test_linkify_romanian(array $fixture): void
    {
        $this->assertSame($fixture['expected'], $this->creator->linkify($fixture['input'], 'ro'));
    }

    public static function roFixtures(): iterable
    {
        foreach (require __DIR__ . '/../fixtures/linkify.ro.php' as $name => $fixture) {
            yield $name => [$fixture];
        }
    }

    /**
     * @param  array{input: string, expected: string}  $fixture
     */
    #[DataProvider('huFixtures')]
    public function test_linkify_hungarian(array $fixture): void
    {
        $this->assertSame($fixture['expected'], $this->creator->linkify($fixture['input'], 'hu'));
    }

    public static function huFixtures(): iterable
    {
        foreach (require __DIR__ . '/../fixtures/linkify.hu.php' as $name => $fixture) {
            yield $name => [$fixture];
        }
    }

    /**
     * @param  array{input: string, expected: string}  $fixture
     */
    #[DataProvider('enFixtures')]
    public function test_linkify_english(array $fixture): void
    {
        $this->assertSame($fixture['expected'], $this->creator->linkify($fixture['input'], 'en'));
    }

    public static function enFixtures(): iterable
    {
        foreach (require __DIR__ . '/../fixtures/linkify.en.php' as $name => $fixture) {
            yield $name => [$fixture];
        }
    }
}
