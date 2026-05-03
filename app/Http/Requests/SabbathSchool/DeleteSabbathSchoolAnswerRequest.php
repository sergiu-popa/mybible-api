<?php

declare(strict_types=1);

namespace App\Http\Requests\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteSabbathSchoolAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->route('content') instanceof SabbathSchoolSegmentContent;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function segmentContent(): SabbathSchoolSegmentContent
    {
        /** @var SabbathSchoolSegmentContent $content */
        $content = $this->route('content');

        return $content;
    }
}
