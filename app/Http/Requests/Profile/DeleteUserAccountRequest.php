<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use App\Domain\User\Profile\DataTransferObjects\DeleteUserAccountData;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteUserAccountRequest extends FormRequest
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
            'password' => ['required', 'string'],
        ];
    }

    public function toData(): DeleteUserAccountData
    {
        /** @var array{password: string} $validated */
        $validated = $this->validated();

        return DeleteUserAccountData::from($validated);
    }
}
