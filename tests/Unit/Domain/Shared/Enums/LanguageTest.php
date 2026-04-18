<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\Enums;

use App\Domain\Shared\Enums\Language;
use PHPUnit\Framework\TestCase;

final class LanguageTest extends TestCase
{
    public function test_it_returns_the_matching_case_for_a_known_value(): void
    {
        $this->assertSame(Language::En, Language::fromRequest('en'));
        $this->assertSame(Language::Ro, Language::fromRequest('ro'));
        $this->assertSame(Language::Hu, Language::fromRequest('hu'));
    }

    public function test_it_returns_the_fallback_for_null(): void
    {
        $this->assertSame(Language::En, Language::fromRequest(null));
    }

    public function test_it_returns_the_fallback_for_unknown_values(): void
    {
        $this->assertSame(Language::En, Language::fromRequest('fr'));
        $this->assertSame(Language::En, Language::fromRequest(''));
    }

    public function test_it_respects_a_custom_fallback(): void
    {
        $this->assertSame(Language::Ro, Language::fromRequest(null, Language::Ro));
        $this->assertSame(Language::Ro, Language::fromRequest('zz', Language::Ro));
    }
}
