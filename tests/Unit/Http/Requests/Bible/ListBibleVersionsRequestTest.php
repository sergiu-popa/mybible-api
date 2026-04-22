<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Bible;

use App\Http\Requests\Bible\ListBibleVersionsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class ListBibleVersionsRequestTest extends TestCase
{
    private function rules(): array
    {
        return (new ListBibleVersionsRequest)->rules();
    }

    public function test_it_allows_omitting_both_fields(): void
    {
        $this->assertTrue(Validator::make([], $this->rules())->passes());
    }

    public function test_it_accepts_supported_languages(): void
    {
        foreach (['en', 'ro', 'hu'] as $language) {
            $this->assertTrue(
                Validator::make(['language' => $language], $this->rules())->passes(),
                "expected `{$language}` to be allowed",
            );
        }
    }

    public function test_it_rejects_unsupported_languages(): void
    {
        $this->assertFalse(Validator::make(['language' => 'fr'], $this->rules())->passes());
    }

    public function test_it_rejects_per_page_over_max(): void
    {
        $this->assertFalse(Validator::make(['per_page' => 101], $this->rules())->passes());
    }

    public function test_it_accepts_per_page_at_max(): void
    {
        $this->assertTrue(Validator::make(['per_page' => 100], $this->rules())->passes());
    }
}
