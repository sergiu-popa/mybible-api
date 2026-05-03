<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\EducationalResources;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateResourceBookChapterRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'audio_cdn_url' => ['sometimes', 'nullable', 'string'],
            'audio_embed' => ['sometimes', 'nullable', 'string'],
            'duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return $validated;
    }
}
