<?php

declare(strict_types=1);

namespace App\Http\Requests\Olympiad;

use App\Domain\Olympiad\DataTransferObjects\SubmitOlympiadAnswerLine;
use App\Domain\Olympiad\DataTransferObjects\SubmitOlympiadAnswersData;
use App\Domain\Olympiad\Models\OlympiadAttempt;
use Illuminate\Foundation\Http\FormRequest;

final class SubmitOlympiadAttemptAnswersRequest extends FormRequest
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
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_uuid' => ['required', 'string', 'uuid'],
            'answers.*.selected_answer_uuid' => ['nullable', 'string', 'uuid'],
        ];
    }

    public function toData(): SubmitOlympiadAnswersData
    {
        /** @var OlympiadAttempt $attempt */
        $attempt = $this->route('attempt');

        /** @var array<int, array{question_uuid: string, selected_answer_uuid?: ?string}> $answers */
        $answers = (array) $this->validated('answers');

        $lines = [];
        foreach ($answers as $entry) {
            $lines[] = new SubmitOlympiadAnswerLine(
                questionUuid: (string) $entry['question_uuid'],
                selectedAnswerUuid: isset($entry['selected_answer_uuid'])
                    ? (string) $entry['selected_answer_uuid']
                    : null,
            );
        }

        return new SubmitOlympiadAnswersData(
            attempt: $attempt,
            lines: $lines,
        );
    }
}
