<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\Auth\DataTransferObjects\RegisterUserData;
use Illuminate\Foundation\Http\FormRequest;

final class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function toData(): RegisterUserData
    {
        /** @var array{name: string, email: string, password: string} $validated */
        $validated = $this->validated();

        return RegisterUserData::from($validated);
    }
}
