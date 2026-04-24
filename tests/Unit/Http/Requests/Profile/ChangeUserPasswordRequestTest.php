<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Profile;

use App\Http\Requests\Profile\ChangeUserPasswordRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Tests\TestCase;

final class ChangeUserPasswordRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_requires_an_authenticated_user(): void
    {
        $request = new ChangeUserPasswordRequest;
        $this->assertFalse($request->authorize());

        $user = User::factory()->create();
        $request->setUserResolver(fn () => $user);
        $this->assertTrue($request->authorize());
    }

    public function test_it_passes_with_a_strong_confirmed_new_password(): void
    {
        $validator = $this->validator([
            'current_password' => 'anything',
            'new_password' => 'New-password1',
            'new_password_confirmation' => 'New-password1',
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_it_rejects_a_weak_new_password(): void
    {
        $validator = $this->validator([
            'current_password' => 'anything',
            'new_password' => 'short',
            'new_password_confirmation' => 'short',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('new_password', $validator->errors()->toArray());
    }

    public function test_it_rejects_new_password_equal_to_current_password(): void
    {
        $validator = $this->validator([
            'current_password' => 'Same-password1',
            'new_password' => 'Same-password1',
            'new_password_confirmation' => 'Same-password1',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('new_password', $validator->errors()->toArray());
    }

    public function test_it_rejects_mismatched_confirmation(): void
    {
        $validator = $this->validator([
            'current_password' => 'Anything1A',
            'new_password' => 'New-password1',
            'new_password_confirmation' => 'Different-password2',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('new_password', $validator->errors()->toArray());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validator(array $payload): Validator
    {
        return ValidatorFacade::make($payload, (new ChangeUserPasswordRequest)->rules());
    }
}
