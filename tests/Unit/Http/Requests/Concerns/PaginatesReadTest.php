<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Concerns;

use App\Http\Requests\Concerns\PaginatesRead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class PaginatesReadTest extends TestCase
{
    public function test_default_per_page_when_param_missing(): void
    {
        $request = $this->makeRequest([]);

        $this->assertSame(30, $request->perPage());
    }

    public function test_clamps_zero_or_negative_to_one(): void
    {
        $this->assertSame(1, $this->makeRequest(['per_page' => '0'])->perPage());
        $this->assertSame(1, $this->makeRequest(['per_page' => '-5'])->perPage());
    }

    public function test_clamps_over_max_to_max(): void
    {
        $this->assertSame(100, $this->makeRequest(['per_page' => '200'])->perPage());
        $this->assertSame(100, $this->makeRequest(['per_page' => '101'])->perPage());
    }

    public function test_returns_value_when_within_range(): void
    {
        $this->assertSame(50, $this->makeRequest(['per_page' => '50'])->perPage());
        $this->assertSame(1, $this->makeRequest(['per_page' => '1'])->perPage());
    }

    public function test_non_numeric_falls_back_to_default(): void
    {
        $this->assertSame(30, $this->makeRequest(['per_page' => 'abc'])->perPage());
    }

    public function test_page_rules_include_page_and_per_page_with_max(): void
    {
        $rules = $this->makeRequest([])->pageRules();

        $this->assertSame(['nullable', 'integer', 'min:1'], $rules['page']);
        $this->assertSame(['nullable', 'integer', 'min:1', 'max:100'], $rules['per_page']);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function makeRequest(array $query): PaginatesReadStubRequest
    {
        $base = Request::create('/test', 'GET', $query);

        return PaginatesReadStubRequest::createFrom($base, new PaginatesReadStubRequest);
    }
}

final class PaginatesReadStubRequest extends FormRequest
{
    use PaginatesRead;
}
