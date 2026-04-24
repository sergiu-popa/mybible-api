<?php

declare(strict_types=1);

namespace App\Http\Requests\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolQuestion;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteSabbathSchoolAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->route('question') instanceof SabbathSchoolQuestion;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
