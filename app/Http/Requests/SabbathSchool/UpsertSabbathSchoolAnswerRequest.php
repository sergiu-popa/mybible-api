<?php

declare(strict_types=1);

namespace App\Http\Requests\SabbathSchool;

use App\Domain\SabbathSchool\DataTransferObjects\UpsertSabbathSchoolAnswerData;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Domain\SabbathSchool\Support\SegmentContentType;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class UpsertSabbathSchoolAnswerRequest extends FormRequest
{
    public const CONTENT_MAX_LENGTH = 10_000;

    /**
     * Authorize the call: the caller must be authenticated and the
     * lesson behind the bound content block must be published. The
     * content-type check happens in `withValidator()` so a non-question
     * payload returns 422 (structurally invalid) rather than 403.
     */
    public function authorize(): bool
    {
        $content = $this->route('content');

        if (! $content instanceof SabbathSchoolSegmentContent || ! $this->user() instanceof User) {
            return false;
        }

        $content->loadMissing(['segment.lesson']);

        $lesson = $content->segment->lesson;

        return $lesson->published_at !== null
            && $lesson->published_at->lessThanOrEqualTo(now());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:' . self::CONTENT_MAX_LENGTH],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $content = $this->route('content');

            if (! $content instanceof SabbathSchoolSegmentContent) {
                return;
            }

            if ($content->type !== SegmentContentType::Question->value) {
                $validator->errors()->add(
                    'content',
                    'Answers can only be attached to question content blocks.',
                );
            }
        });
    }

    public function toData(): UpsertSabbathSchoolAnswerData
    {
        /** @var SabbathSchoolSegmentContent $content */
        $content = $this->route('content');

        /** @var User $user */
        $user = $this->user();

        /** @var string $body */
        $body = $this->validated('content');

        return new UpsertSabbathSchoolAnswerData(
            user: $user,
            segmentContent: $content,
            content: $body,
        );
    }
}
