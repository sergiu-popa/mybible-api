<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Profile;

use App\Http\Requests\Profile\UploadUserAvatarRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Tests\TestCase;

final class UploadUserAvatarRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_requires_an_authenticated_user(): void
    {
        $request = new UploadUserAvatarRequest;
        $this->assertFalse($request->authorize());

        $user = User::factory()->create();
        $request->setUserResolver(fn () => $user);
        $this->assertTrue($request->authorize());
    }

    public function test_it_passes_with_a_jpeg_image(): void
    {
        $validator = $this->validator([
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_it_fails_when_avatar_is_missing(): void
    {
        $validator = $this->validator([]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('avatar', $validator->errors()->toArray());
    }

    public function test_it_rejects_a_gif(): void
    {
        $validator = $this->validator([
            'avatar' => UploadedFile::fake()->image('avatar.gif'),
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('avatar', $validator->errors()->toArray());
    }

    public function test_it_rejects_files_larger_than_5_mb(): void
    {
        $validator = $this->validator([
            'avatar' => UploadedFile::fake()->image('avatar.png')->size(5 * 1024 + 1),
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('avatar', $validator->errors()->toArray());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validator(array $payload): Validator
    {
        return ValidatorFacade::make($payload, (new UploadUserAvatarRequest)->rules());
    }
}
