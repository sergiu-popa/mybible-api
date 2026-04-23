<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\News;

use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use App\Http\Requests\News\ListNewsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class ListNewsRequestTest extends TestCase
{
    public function test_it_accepts_a_valid_language_and_pagination(): void
    {
        $this->assertTrue($this->validate([
            'language' => 'ro',
            'page' => 2,
            'per_page' => 30,
        ])->passes());
    }

    public function test_it_accepts_missing_fields(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_it_rejects_unknown_language(): void
    {
        $validator = $this->validate(['language' => 'fr']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('language', $validator->errors()->toArray());
    }

    public function test_it_enforces_the_max_per_page(): void
    {
        $validator = $this->validate(['per_page' => ListNewsRequest::MAX_PER_PAGE + 1]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }

    public function test_per_page_returns_the_default_when_absent_or_invalid(): void
    {
        $request = ListNewsRequest::create('/api/v1/news', 'GET', []);

        $this->assertSame(ListNewsRequest::DEFAULT_PER_PAGE, $request->perPage());

        $request = ListNewsRequest::create('/api/v1/news?per_page=abc', 'GET');
        $this->assertSame(ListNewsRequest::DEFAULT_PER_PAGE, $request->perPage());
    }

    public function test_per_page_clamps_above_and_below_bounds(): void
    {
        $request = ListNewsRequest::create('/api/v1/news?per_page=0', 'GET');
        $this->assertSame(1, $request->perPage());

        $request = ListNewsRequest::create('/api/v1/news?per_page=999', 'GET');
        $this->assertSame(ListNewsRequest::MAX_PER_PAGE, $request->perPage());
    }

    public function test_resolved_language_prefers_explicit_query_parameter(): void
    {
        $request = ListNewsRequest::create('/api/v1/news?language=ro', 'GET');

        $this->assertSame(Language::Ro, $request->resolvedLanguage());
    }

    public function test_resolved_language_falls_back_to_middleware_attribute(): void
    {
        $request = ListNewsRequest::create('/api/v1/news', 'GET');
        $request->attributes->set(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::Hu);

        $this->assertSame(Language::Hu, $request->resolvedLanguage());
    }

    public function test_resolved_language_defaults_to_english_when_nothing_is_set(): void
    {
        $request = ListNewsRequest::create('/api/v1/news', 'GET');

        $this->assertSame(Language::En, $request->resolvedLanguage());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validate(array $payload): \Illuminate\Validation\Validator
    {
        $request = new ListNewsRequest;

        /** @var \Illuminate\Validation\Validator $validator */
        $validator = Validator::make($payload, $request->rules());

        return $validator;
    }
}
