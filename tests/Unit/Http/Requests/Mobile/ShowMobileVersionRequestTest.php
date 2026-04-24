<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Mobile;

use App\Http\Requests\Mobile\ShowMobileVersionRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class ShowMobileVersionRequestTest extends TestCase
{
    public function test_it_accepts_ios(): void
    {
        $this->assertTrue($this->validate(['platform' => 'ios'])->passes());
    }

    public function test_it_accepts_android(): void
    {
        $this->assertTrue($this->validate(['platform' => 'android'])->passes());
    }

    public function test_it_rejects_missing_platform(): void
    {
        $validator = $this->validate([]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('platform', $validator->errors()->toArray());
    }

    public function test_it_rejects_unknown_platform(): void
    {
        $validator = $this->validate(['platform' => 'windows']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('platform', $validator->errors()->toArray());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validate(array $payload): \Illuminate\Validation\Validator
    {
        $request = new ShowMobileVersionRequest;

        /** @var \Illuminate\Validation\Validator $validator */
        $validator = Validator::make($payload, $request->rules());

        return $validator;
    }
}
