<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Analytics;

use App\Domain\Analytics\DataTransferObjects\AnalyticsRangeQueryData;
use App\Domain\Analytics\DataTransferObjects\ReadingPlanFunnelQueryData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ShowReadingPlanFunnelRequest extends FormRequest
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
            'plan_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toData(): ReadingPlanFunnelQueryData
    {
        $range = new AnalyticsRangeQueryData(
            from: CarbonImmutable::parse((string) $this->validated('from'))->startOfDay(),
            to: CarbonImmutable::parse((string) $this->validated('to'))->endOfDay(),
            period: 'day',
        );

        $planId = $this->validated('plan_id');

        return new ReadingPlanFunnelQueryData(
            range: $range,
            planId: $planId !== null ? (int) $planId : null,
        );
    }
}
