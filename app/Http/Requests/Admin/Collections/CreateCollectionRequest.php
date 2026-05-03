<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Collections;

use App\Domain\Collections\DataTransferObjects\CreateCollectionData;
use Illuminate\Foundation\Http\FormRequest;

final class CreateCollectionRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', 'unique:collections,slug'],
            'name' => ['required', 'string', 'max:255'],
            'language' => ['required', 'string', 'size:2'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function toData(): CreateCollectionData
    {
        $v = $this->validated();

        return new CreateCollectionData(
            slug: (string) $v['slug'],
            name: (string) $v['name'],
            language: (string) $v['language'],
            position: isset($v['position']) ? (int) $v['position'] : 0,
        );
    }
}
