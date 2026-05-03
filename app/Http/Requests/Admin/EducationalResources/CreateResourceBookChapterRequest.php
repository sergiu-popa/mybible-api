<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\EducationalResources;

use App\Domain\EducationalResources\DataTransferObjects\ResourceBookChapterData;
use Illuminate\Foundation\Http\FormRequest;

final class CreateResourceBookChapterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'audio_cdn_url' => ['nullable', 'string'],
            'audio_embed' => ['nullable', 'string'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'position' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toData(): ResourceBookChapterData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ResourceBookChapterData::from($validated);
    }
}
