<?php

declare(strict_types=1);

namespace App\Http\Requests\SabbathSchool;

use App\Domain\SabbathSchool\DataTransferObjects\UpsertSabbathSchoolAnswerData;
use App\Domain\SabbathSchool\Models\SabbathSchoolQuestion;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class UpsertSabbathSchoolAnswerRequest extends FormRequest
{
    public const CONTENT_MAX_LENGTH = 10_000;

    /**
     * Guards the published-lesson invariant: a question can only accept
     * answers if its lesson is published. Draft content is invisible to the
     * catalog endpoints so attaching answers to it is rejected up-front.
     */
    public function authorize(): bool
    {
        $question = $this->route('question');

        if (! $question instanceof SabbathSchoolQuestion || ! $this->user() instanceof User) {
            return false;
        }

        $question->loadMissing(['segment.lesson']);

        $lesson = $question->segment->lesson;

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

    public function toData(): UpsertSabbathSchoolAnswerData
    {
        /** @var SabbathSchoolQuestion $question */
        $question = $this->route('question');

        /** @var User $user */
        $user = $this->user();

        /** @var string $content */
        $content = $this->validated('content');

        return new UpsertSabbathSchoolAnswerData(
            user: $user,
            question: $question,
            content: $content,
        );
    }
}
