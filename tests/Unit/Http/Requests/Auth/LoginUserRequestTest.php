<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Auth;

use App\Http\Requests\Auth\LoginUserRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class LoginUserRequestTest extends TestCase
{
    public function test_it_authorizes(): void
    {
        $this->assertTrue((new LoginUserRequest)->authorize());
    }

    public function test_it_passes_with_valid_payload(): void
    {
        $this->assertFalse($this->validator([
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
        ])->fails());
    }

    public function test_it_fails_when_fields_are_missing(): void
    {
        $validator = $this->validator([]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_it_fails_with_invalid_email(): void
    {
        $validator = $this->validator([
            'email' => 'not-an-email',
            'password' => 'secret-pass',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validator(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($payload, (new LoginUserRequest)->rules());
    }
}
