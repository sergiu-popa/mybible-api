<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Analytics;

use App\Domain\Analytics\DataTransferObjects\AnalyticsRangeQueryData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShowDauMauRequest extends FormRequest
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
        ];
    }

    public function toData(): AnalyticsRangeQueryData
    {
        return new AnalyticsRangeQueryData(
            from: CarbonImmutable::parse((string) $this->validated('from'))->startOfDay(),
            to: CarbonImmutable::parse((string) $this->validated('to'))->endOfDay(),
            period: (string) $this->validated('period'),
        );
    }
}
