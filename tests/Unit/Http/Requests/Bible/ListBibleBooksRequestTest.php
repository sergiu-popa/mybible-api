<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Bible;

use App\Http\Requests\Bible\ListBibleBooksRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class ListBibleBooksRequestTest extends TestCase
{
    private function rules(): array
    {
        return (new ListBibleBooksRequest)->rules();
    }

    public function test_it_allows_omitting_language(): void
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
}
