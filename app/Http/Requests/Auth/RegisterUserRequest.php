<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\Auth\DataTransferObjects\RegisterUserData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // Soft-deleted rows share the `(email, deleted_at)` composite
                // unique index with live rows, so this rule must ignore
                // soft-deleted users — otherwise a soft-deleted account
                // would permanently block re-registration.
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];
    }

    public function toData(): RegisterUserData
    {
        /** @var array{name: string, email: string, password: string} $validated */
        $validated = $this->validated();

        return RegisterUserData::from($validated);
    }
}
