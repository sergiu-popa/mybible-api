<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\Auth\DataTransferObjects\ResetPasswordData;
use Illuminate\Foundation\Http\FormRequest;

final class ResetPasswordRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function toData(): ResetPasswordData
    {
        /** @var array{email: string, token: string, password: string} $validated */
        $validated = $this->validated();

        return ResetPasswordData::from($validated);
    }
}
