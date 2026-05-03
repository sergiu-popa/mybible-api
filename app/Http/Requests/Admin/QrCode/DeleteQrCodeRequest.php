<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\QrCode;

use Illuminate\Foundation\Http\FormRequest;

final class DeleteQrCodeRequest extends FormRequest
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
        return [];
    }
}
