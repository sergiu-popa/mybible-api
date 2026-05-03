<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Collections;

use App\Domain\Collections\DataTransferObjects\UpdateCollectionData;
use App\Domain\Collections\Models\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCollectionRequest extends FormRequest
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
        $collection = $this->route('collection');
        $ignoreId = $collection instanceof Collection ? $collection->id : null;

        return [
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique('collections', 'slug')->ignore($ignoreId)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'language' => ['sometimes', 'required', 'string', 'size:2'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function toData(): UpdateCollectionData
    {
        $v = $this->validated();

        return new UpdateCollectionData(
            slug: array_key_exists('slug', $v) ? (string) $v['slug'] : null,
            name: array_key_exists('name', $v) ? (string) $v['name'] : null,
            language: array_key_exists('language', $v) ? (string) $v['language'] : null,
            position: array_key_exists('position', $v) ? (int) $v['position'] : null,
            slugProvided: array_key_exists('slug', $v),
            nameProvided: array_key_exists('name', $v),
            languageProvided: array_key_exists('language', $v),
            positionProvided: array_key_exists('position', $v),
        );
    }
}
