<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Profile;

use App\Http\Requests\Profile\DeleteUserAccountRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Tests\TestCase;

final class DeleteUserAccountRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_requires_an_authenticated_user(): void
    {
        $request = new DeleteUserAccountRequest;
        $this->assertFalse($request->authorize());

        $user = User::factory()->create();
        $request->setUserResolver(fn () => $user);
        $this->assertTrue($request->authorize());
    }

    public function test_it_passes_with_a_non_empty_password(): void
    {
        $validator = $this->validator(['password' => 'anything']);

        $this->assertFalse($validator->fails());
    }

    public function test_it_fails_when_password_is_missing(): void
    {
        $validator = $this->validator([]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validator(array $payload): Validator
    {
        return ValidatorFacade::make($payload, (new DeleteUserAccountRequest)->rules());
    }
}
