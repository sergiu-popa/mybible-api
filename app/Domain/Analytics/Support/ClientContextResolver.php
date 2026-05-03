<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Support;

use App\Domain\Analytics\DataTransferObjects\ResourceDownloadContextData;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Http\Request;

final class ClientContextResolver
{
    public static function fromRequest(Request $request): ResourceDownloadContextData
    {
        $userId = $request->user()?->getAuthIdentifier();
        $userIdInt = is_numeric($userId) ? (int) $userId : null;

        $headerDeviceId = $request->header('X-Device-Id');
        $headerDeviceId = is_string($headerDeviceId) && $headerDeviceId !== '' ? $headerDeviceId : null;

        $bodyDeviceId = $request->input('device_id');
        $bodyDeviceId = is_string($bodyDeviceId) && $bodyDeviceId !== '' ? $bodyDeviceId : null;

        $deviceId = $headerDeviceId ?? $bodyDeviceId;

        if ($deviceId !== null && mb_strlen($deviceId) > 64) {
            $deviceId = mb_substr($deviceId, 0, 64);
        }

        $language = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY);
        $languageCode = $language instanceof Language ? $language->value : null;

        $bodySource = $request->input('source');
        if (is_string($bodySource) && in_array($bodySource, ['ios', 'android', 'web'], true)) {
            $source = $bodySource;
        } else {
            $source = self::inferSourceFromUserAgent((string) $request->userAgent());
        }

        return new ResourceDownloadContextData(
            userId: $userIdInt,
            deviceId: $deviceId,
            language: $languageCode,
            source: $source,
        );
    }

    private static function inferSourceFromUserAgent(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }

        if (str_contains($userAgent, 'MyBibleMobile')) {
            if (str_contains($userAgent, 'ios')) {
                return 'ios';
            }

            if (str_contains($userAgent, 'android')) {
                return 'android';
            }

            return null;
        }

        if (preg_match('/(Mozilla|Chrome|Safari|Firefox|Edge)/i', $userAgent) === 1) {
            return 'web';
        }

        return null;
    }
}
