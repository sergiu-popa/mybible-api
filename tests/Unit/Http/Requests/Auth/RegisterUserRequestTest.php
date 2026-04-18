<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Auth;

use App\Http\Requests\Auth\RegisterUserRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class RegisterUserRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_authorizes(): void
    {
        $this->assertTrue((new RegisterUserRequest)->authorize());
    }

    public function test_it_passes_with_valid_payload(): void
    {
        $this->assertFalse($this->validator([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->fails());
    }

    public function test_it_fails_when_fields_are_missing(): void
    {
        $validator = $this->validator([]);

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey('name', $validator->errors()->toArray());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_it_fails_with_invalid_email(): void
    {
        $validator = $this->validator([
            'name' => 'Jane',
            'email' => 'not-an-email',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_it_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $validator = $this->validator([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_it_fails_with_short_password(): void
    {
        $validator = $this->validator([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_it_fails_when_password_confirmation_does_not_match(): void
    {
        $validator = $this->validator([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'other-pass',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validator(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($payload, (new RegisterUserRequest)->rules());
    }
}
