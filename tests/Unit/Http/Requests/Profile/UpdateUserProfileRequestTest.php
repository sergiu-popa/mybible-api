<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Profile;

use App\Http\Requests\Profile\UpdateUserProfileRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Tests\TestCase;

final class UpdateUserProfileRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_requires_an_authenticated_user(): void
    {
        $request = new UpdateUserProfileRequest;
        $this->assertFalse($request->authorize());

        $user = User::factory()->create();
        $request->setUserResolver(fn () => $user);
        $this->assertTrue($request->authorize());
    }

    public function test_it_passes_with_a_single_field(): void
    {
        $validator = $this->buildValidator(['name' => 'Jane']);

        $this->assertFalse($validator->fails());
    }

    public function test_it_fails_when_all_fields_are_null(): void
    {
        $validator = $this->buildValidator([]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_it_rejects_unknown_language(): void
    {
        $validator = $this->buildValidator(['language' => 'de']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('language', $validator->errors()->toArray());
    }

    public function test_it_accepts_supported_language(): void
    {
        $validator = $this->buildValidator(['language' => 'ro']);

        $this->assertFalse($validator->fails());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildValidator(array $payload): Validator
    {
        $request = new UpdateUserProfileRequest;
        $request->merge($payload);

        $validator = ValidatorFacade::make(
            $payload,
            $request->rules(),
            $request->messages(),
        );

        $request->withValidator($validator);

        // Trigger the `after` hooks.
        $validator->fails();

        return $validator;
    }
}
