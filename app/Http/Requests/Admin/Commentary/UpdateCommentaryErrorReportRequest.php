<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Commentary;

use App\Domain\Commentary\Enums\CommentaryErrorReportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCommentaryErrorReportRequest extends FormRequest
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
            'status' => [
                'required',
                'string',
                Rule::in(array_map(
                    fn (CommentaryErrorReportStatus $s): string => $s->value,
                    CommentaryErrorReportStatus::cases(),
                )),
            ],
        ];
    }
}
