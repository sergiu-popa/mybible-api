<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\QrCode;

use App\Domain\QrCode\DataTransferObjects\UpdateQrCodeData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateQrCodeRequest extends FormRequest
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
            'place' => ['sometimes', 'required', 'string', 'max:255'],
            'base_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'source' => ['sometimes', 'required', 'string', 'max:255'],
            'destination' => ['sometimes', 'required', 'url', 'max:255'],
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'content' => ['sometimes', 'required', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'image_path' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): UpdateQrCodeData
    {
        $v = $this->validated();

        return new UpdateQrCodeData(
            place: array_key_exists('place', $v) ? (string) $v['place'] : null,
            baseUrl: array_key_exists('base_url', $v) && $v['base_url'] !== null ? (string) $v['base_url'] : null,
            source: array_key_exists('source', $v) ? (string) $v['source'] : null,
            destination: array_key_exists('destination', $v) ? (string) $v['destination'] : null,
            name: array_key_exists('name', $v) ? (string) $v['name'] : null,
            content: array_key_exists('content', $v) ? (string) $v['content'] : null,
            description: array_key_exists('description', $v) && $v['description'] !== null ? (string) $v['description'] : null,
            reference: array_key_exists('reference', $v) && $v['reference'] !== null ? (string) $v['reference'] : null,
            imagePath: array_key_exists('image_path', $v) && $v['image_path'] !== null ? (string) $v['image_path'] : null,
            placeProvided: array_key_exists('place', $v),
            baseUrlProvided: array_key_exists('base_url', $v),
            sourceProvided: array_key_exists('source', $v),
            destinationProvided: array_key_exists('destination', $v),
            nameProvided: array_key_exists('name', $v),
            contentProvided: array_key_exists('content', $v),
            descriptionProvided: array_key_exists('description', $v),
            referenceProvided: array_key_exists('reference', $v),
            imagePathProvided: array_key_exists('image_path', $v),
        );
    }
}
