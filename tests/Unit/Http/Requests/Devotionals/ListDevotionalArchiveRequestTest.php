<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Devotionals;

use App\Http\Requests\Devotionals\ListDevotionalArchiveRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class ListDevotionalArchiveRequestTest extends TestCase
{
    public function test_it_accepts_valid_payload(): void
    {
        $this->assertTrue($this->validate([
            'type' => 'kids',
            'from' => '2026-01-01',
            'to' => '2026-04-22',
            'per_page' => 10,
        ])->passes());
    }

    public function test_it_requires_type(): void
    {
        $validator = $this->validate([]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('type', $validator->errors()->toArray());
    }

    public function test_it_rejects_to_before_from(): void
    {
        $validator = $this->validate([
            'type' => 'adults',
            'from' => '2026-05-01',
            'to' => '2026-04-22',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('to', $validator->errors()->toArray());
    }

    public function test_it_rejects_per_page_over_max(): void
    {
        $validator = $this->validate([
            'type' => 'adults',
            'per_page' => ListDevotionalArchiveRequest::MAX_PER_PAGE + 1,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validate(array $payload): \Illuminate\Validation\Validator
    {
        $request = new ListDevotionalArchiveRequest;

        /** @var \Illuminate\Validation\Validator $validator */
        $validator = Validator::make($payload, $request->rules());

        return $validator;
    }
}
