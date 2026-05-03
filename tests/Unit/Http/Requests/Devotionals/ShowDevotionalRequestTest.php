<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Devotionals;

use App\Http\Requests\Devotionals\ShowDevotionalRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class ShowDevotionalRequestTest extends TestCase
{
    public function test_it_accepts_valid_payload(): void
    {
        $this->assertTrue($this->validate([
            'language' => 'ro',
            'type' => 'adults',
            'date' => '2026-04-22',
        ])->passes());
    }

    public function test_it_accepts_missing_date(): void
    {
        $this->assertTrue($this->validate([
            'type' => 'adults',
        ])->passes());
    }

    public function test_it_accepts_admin_defined_slug(): void
    {
        $this->assertTrue($this->validate([
            'type' => 'youth',
        ])->passes());
    }

    public function test_it_requires_type(): void
    {
        $validator = $this->validate([]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('type', $validator->errors()->toArray());
    }

    public function test_it_rejects_malformed_date(): void
    {
        $validator = $this->validate(['type' => 'adults', 'date' => '22/04/2026']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('date', $validator->errors()->toArray());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validate(array $payload): \Illuminate\Validation\Validator
    {
        $request = new ShowDevotionalRequest;

        /** @var \Illuminate\Validation\Validator $validator */
        $validator = Validator::make($payload, $request->rules());

        return $validator;
    }
}
