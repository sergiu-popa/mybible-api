<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ReadingPlans\Support;

use App\Domain\ReadingPlans\Support\LanguageResolver;
use App\Domain\Shared\Enums\Language;
use PHPUnit\Framework\TestCase;

final class LanguageResolverTest extends TestCase
{
    public function test_it_returns_the_requested_language_when_present(): void
    {
        $map = ['en' => 'Hello', 'ro' => 'Salut', 'hu' => 'Szia'];

        $this->assertSame('Salut', LanguageResolver::resolve($map, Language::Ro));
    }

    public function test_it_falls_back_to_english_when_requested_language_is_missing(): void
    {
        $map = ['en' => 'Hello', 'ro' => 'Salut'];

        $this->assertSame('Hello', LanguageResolver::resolve($map, Language::Hu));
    }

    public function test_it_returns_null_when_neither_language_is_available(): void
    {
        $this->assertNull(LanguageResolver::resolve([], Language::En));
        $this->assertNull(LanguageResolver::resolve(['ro' => 'Salut'], Language::Hu, Language::En));
    }

    public function test_it_treats_empty_strings_as_missing(): void
    {
        $map = ['en' => 'Hello', 'ro' => ''];

        $this->assertSame('Hello', LanguageResolver::resolve($map, Language::Ro));
    }
}
