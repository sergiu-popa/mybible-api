<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Olympiad;

use App\Domain\Olympiad\DataTransferObjects\ListOlympiadAttemptsFilter;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;

final class ListAdminOlympiadAttemptsRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

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
            'language' => ['nullable', 'string', 'size:2'],
            'book' => ['nullable', 'string', 'max:8'],
            'chapters' => ['nullable', 'string', 'max:32'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
        ];
    }

    public function pageNumber(): int
    {
        $value = $this->query('page');

        return is_numeric($value) ? max(1, (int) $value) : 1;
    }

    public function perPage(): int
    {
        $value = $this->query('per_page');

        return is_numeric($value)
            ? max(1, min(self::MAX_PER_PAGE, (int) $value))
            : self::DEFAULT_PER_PAGE;
    }

    public function filter(): ListOlympiadAttemptsFilter
    {
        $languageRaw = $this->query('language');
        $language = is_string($languageRaw) && $languageRaw !== ''
            ? Language::tryFrom($languageRaw)
            : null;

        $book = $this->query('book');
        $chapters = $this->query('chapters');
        $userId = $this->query('user_id');

        return new ListOlympiadAttemptsFilter(
            language: $language,
            book: is_string($book) && $book !== '' ? $book : null,
            chaptersLabel: is_string($chapters) && $chapters !== '' ? $chapters : null,
            userId: is_numeric($userId) ? (int) $userId : null,
        );
    }
}
