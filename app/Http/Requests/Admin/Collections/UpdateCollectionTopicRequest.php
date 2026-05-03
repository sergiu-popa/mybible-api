<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Collections;

use App\Domain\Collections\DataTransferObjects\UpdateCollectionTopicData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCollectionTopicRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'image_cdn_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function toData(): UpdateCollectionTopicData
    {
        $v = $this->validated();

        return new UpdateCollectionTopicData(
            name: array_key_exists('name', $v) ? (string) $v['name'] : null,
            description: array_key_exists('description', $v) && $v['description'] !== null ? (string) $v['description'] : null,
            imageCdnUrl: array_key_exists('image_cdn_url', $v) && $v['image_cdn_url'] !== null ? (string) $v['image_cdn_url'] : null,
            position: array_key_exists('position', $v) ? (int) $v['position'] : null,
            nameProvided: array_key_exists('name', $v),
            descriptionProvided: array_key_exists('description', $v),
            imageCdnUrlProvided: array_key_exists('image_cdn_url', $v),
            positionProvided: array_key_exists('position', $v),
        );
    }
}
