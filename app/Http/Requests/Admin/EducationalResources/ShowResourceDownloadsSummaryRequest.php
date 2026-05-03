<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\EducationalResources;

use App\Domain\Analytics\DataTransferObjects\SummaryQueryData;
use App\Domain\Analytics\Models\ResourceDownload;
use App\Domain\Shared\Enums\Language;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShowResourceDownloadsSummaryRequest extends FormRequest
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
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'group_by' => ['required', 'string', Rule::in(['day', 'week', 'month'])],
            'downloadable_type' => ['nullable', 'string', Rule::in([
                ResourceDownload::TYPE_EDUCATIONAL_RESOURCE,
                ResourceDownload::TYPE_RESOURCE_BOOK,
                ResourceDownload::TYPE_RESOURCE_BOOK_CHAPTER,
            ])],
            'language' => ['nullable', 'string', Rule::in(array_map(
                static fn (Language $l): string => $l->value,
                Language::cases(),
            ))],
        ];
    }

    public function toData(): SummaryQueryData
    {
        return new SummaryQueryData(
            from: CarbonImmutable::parse((string) $this->validated('from'))->startOfDay(),
            to: CarbonImmutable::parse((string) $this->validated('to'))->endOfDay(),
            groupBy: (string) $this->validated('group_by'),
            downloadableType: $this->validated('downloadable_type') !== null
                ? (string) $this->validated('downloadable_type')
                : null,
            language: $this->validated('language') !== null
                ? (string) $this->validated('language')
                : null,
        );
    }
}
