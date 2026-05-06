<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Analytics;

use App\Domain\Analytics\DataTransferObjects\AnalyticsRangeQueryData;
use App\Domain\Analytics\DataTransferObjects\EventCountsQueryData;
use App\Domain\Analytics\Enums\EventType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListAnalyticsEventCountsRequest extends FormRequest
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
            'period' => ['required', 'string', Rule::in(['day', 'week', 'month'])],
            'event_type' => ['required', 'string', Rule::enum(EventType::class)],
            'group_by' => ['nullable', 'string', Rule::in(['language', 'subject_id'])],
        ];
    }

    public function toData(): EventCountsQueryData
    {
        $range = new AnalyticsRangeQueryData(
            from: CarbonImmutable::parse((string) $this->validated('from'))->startOfDay(),
            to: CarbonImmutable::parse((string) $this->validated('to'))->endOfDay(),
            period: (string) $this->validated('period'),
        );

        $groupBy = $this->validated('group_by');

        return new EventCountsQueryData(
            range: $range,
            eventType: EventType::from((string) $this->validated('event_type')),
            groupBy: is_string($groupBy) && $groupBy !== '' ? $groupBy : null,
        );
    }
}
