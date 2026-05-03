<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Collections;

use App\Domain\Collections\DataTransferObjects\CreateCollectionTopicData;
use App\Domain\Collections\Models\Collection;
use Illuminate\Foundation\Http\FormRequest;

final class CreateCollectionTopicRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image_cdn_url' => ['nullable', 'url', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function toData(): CreateCollectionTopicData
    {
        $v = $this->validated();

        $collection = $this->route('collection');
        $collectionId = $collection instanceof Collection ? $collection->id : null;
        $language = $collection instanceof Collection ? $collection->language : 'ro';

        return new CreateCollectionTopicData(
            collectionId: $collectionId,
            language: $language,
            name: (string) $v['name'],
            description: isset($v['description']) ? (string) $v['description'] : null,
            imageCdnUrl: isset($v['image_cdn_url']) ? (string) $v['image_cdn_url'] : null,
            position: isset($v['position']) ? (int) $v['position'] : 0,
        );
    }
}
