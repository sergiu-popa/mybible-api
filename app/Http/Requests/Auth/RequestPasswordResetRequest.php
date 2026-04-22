<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\Auth\DataTransferObjects\RequestPasswordResetData;
use Illuminate\Foundation\Http\FormRequest;

final class RequestPasswordResetRequest extends FormRequest
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
        ];
    }

    public function toData(): RequestPasswordResetData
    {
        /** @var array{email: string} $validated */
        $validated = $this->validated();

        return RequestPasswordResetData::from($validated);
    }
}
