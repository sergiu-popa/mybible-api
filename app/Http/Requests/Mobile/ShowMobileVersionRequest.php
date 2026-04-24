<?php

declare(strict_types=1);

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

final class ShowMobileVersionRequest extends FormRequest
{
    public const PLATFORM_IOS = 'ios';

    public const PLATFORM_ANDROID = 'android';

    /**
     * @var list<string>
     */
    public const ALLOWED_PLATFORMS = [self::PLATFORM_IOS, self::PLATFORM_ANDROID];

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
            'platform' => ['required', 'string', 'in:' . implode(',', self::ALLOWED_PLATFORMS)],
        ];
    }

    public function platform(): string
    {
        /** @var string $platform */
        $platform = $this->validated('platform');

        return $platform;
    }
}
