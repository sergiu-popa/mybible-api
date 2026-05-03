<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\QrCode;

use App\Domain\QrCode\DataTransferObjects\CreateQrCodeData;
use Illuminate\Foundation\Http\FormRequest;

final class CreateQrCodeRequest extends FormRequest
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
            'place' => ['required', 'string', 'max:255'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'source' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'url', 'max:255'],
            'name' => ['required', 'string', 'max:50'],
            'content' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
            'image_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): CreateQrCodeData
    {
        $v = $this->validated();

        return new CreateQrCodeData(
            place: (string) $v['place'],
            baseUrl: isset($v['base_url']) ? (string) $v['base_url'] : null,
            source: (string) $v['source'],
            destination: (string) $v['destination'],
            name: (string) $v['name'],
            content: (string) $v['content'],
            description: isset($v['description']) ? (string) $v['description'] : null,
            reference: isset($v['reference']) ? (string) $v['reference'] : null,
            imagePath: isset($v['image_path']) ? (string) $v['image_path'] : null,
        );
    }
}
