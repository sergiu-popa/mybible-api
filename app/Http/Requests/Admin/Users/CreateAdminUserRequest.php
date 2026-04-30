<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Users;

use App\Domain\Admin\Users\DataTransferObjects\CreateAdminUserData;
use Illuminate\Foundation\Http\FormRequest;

final class CreateAdminUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,NULL,id,deleted_at,NULL'],
            'languages' => ['sometimes', 'array'],
            'languages.*' => ['string', 'size:2'],
            'ui_locale' => ['sometimes', 'nullable', 'string', 'size:2'],
            'is_super' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): CreateAdminUserData
    {
        /** @var array{name: string, email: string, languages?: list<string>, ui_locale?: string|null, is_super?: bool} $validated */
        $validated = $this->validated();

        return CreateAdminUserData::from($validated);
    }
}
