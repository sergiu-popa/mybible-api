<?php

declare(strict_types=1);

namespace App\Http\Requests\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolQuestion;
use Illuminate\Foundation\Http\FormRequest;

final class ShowSabbathSchoolAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user may read their own answer; cross-user access
        // is prevented at the query level (see controller).
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
