<?php

declare(strict_types=1);

namespace App\Http\Requests\ReadingPlans;

use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;

final class ShowReadingPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'language' => ['nullable', 'string'],
        ];
    }

    public function language(): Language
    {
        $value = $this->query('language');

        return Language::fromRequest(is_string($value) ? $value : null);
    }
}
