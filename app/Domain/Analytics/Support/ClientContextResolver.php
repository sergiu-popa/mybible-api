<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Support;

use App\Domain\Analytics\DataTransferObjects\ResourceDownloadContextData;
use App\Domain\Analytics\Enums\EventSource;
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

        // Per stakeholder F: User-Agent is the source of truth for
        // `source`. Body-supplied `source` is accepted only as a hint
        // when the User-Agent is unparseable, so a malicious client
        // cannot mis-attribute its events.
        $source = EventSource::fromUserAgent((string) $request->userAgent());

        if ($source === null) {
            $bodySource = $request->input('source');
            if (is_string($bodySource)) {
                $source = EventSource::tryFrom($bodySource);
            }
        }

        return new ResourceDownloadContextData(
            userId: $userIdInt,
            deviceId: $deviceId,
            language: $languageCode,
            source: $source,
        );
    }
}
