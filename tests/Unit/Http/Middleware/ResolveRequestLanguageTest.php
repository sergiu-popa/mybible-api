<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class ResolveRequestLanguageTest extends TestCase
{
    public function test_it_binds_the_resolved_language_for_a_supported_value(): void
    {
        $captured = $this->runMiddleware(['language' => 'ro']);

        $this->assertSame(Language::Ro, $captured);
    }

    public function test_it_falls_back_to_english_for_an_unsupported_value(): void
    {
        $captured = $this->runMiddleware(['language' => 'fr']);

        $this->assertSame(Language::En, $captured);
    }

    public function test_it_falls_back_to_english_when_language_is_missing(): void
    {
        $captured = $this->runMiddleware([]);

        $this->assertSame(Language::En, $captured);
    }

    public function test_it_falls_back_to_english_when_language_is_not_a_string(): void
    {
        $captured = $this->runMiddleware(['language' => ['ro']]);

        $this->assertSame(Language::En, $captured);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function runMiddleware(array $query): Language
    {
        $request = Request::create('/any', 'GET', $query);

        (new ResolveRequestLanguage)->handle($request, fn (Request $_) => new Response);

        $value = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY);

        $this->assertInstanceOf(Language::class, $value);

        return $value;
    }
}
