<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use App\Domain\User\Profile\DataTransferObjects\ChangeUserPasswordData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ChangeUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password' => [
                'required',
                'string',
                Password::defaults(),
                'confirmed',
                'different:current_password',
            ],
        ];
    }

    public function toData(): ChangeUserPasswordData
    {
        /** @var array{current_password: string, new_password: string} $validated */
        $validated = $this->validated();

        return ChangeUserPasswordData::from($validated);
    }
}
