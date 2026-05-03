<?php

declare(strict_types=1);

namespace App\Http\Requests\Olympiad;

use App\Domain\Olympiad\DataTransferObjects\StartOlympiadAttemptData;
use App\Domain\Reference\ChapterRange;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class StartOlympiadAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'book' => ['required', 'string', 'min:2', 'max:8'],
            'chapters' => ['required', 'string', 'max:32'],
        ];
    }

    public function toData(): StartOlympiadAttemptData
    {
        /** @var User $user */
        $user = $this->user();

        $book = (string) $this->input('book');
        $range = ChapterRange::fromSegment((string) $this->input('chapters'));

        $resolved = $this->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);
        $language = $resolved instanceof Language ? $resolved : Language::En;

        return new StartOlympiadAttemptData(
            user: $user,
            book: $book,
            range: $range,
            language: $language,
        );
    }
}
