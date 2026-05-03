<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Support;

use App\Domain\Mobile\Models\MobileVersion;
use Illuminate\Support\Facades\Cache;

final class MobileVersionsRepository
{
    private const CACHE_TTL = 300;

    /** @var array<string, array<string, mixed>> */
    private array $memo = [];

    /**
     * @return array<string, mixed>
     */
    public function payloadFor(string $platform): array
    {
        if (isset($this->memo[$platform])) {
            return $this->memo[$platform];
        }

        $key = sprintf('mobile-versions:payload:%s', $platform);

        /** @var array<string, mixed> $payload */
        $payload = Cache::remember($key, self::CACHE_TTL, function () use ($platform): array {
            return $this->buildPayload($platform);
        });

        $this->memo[$platform] = $payload;

        return $payload;
    }

    public function latestVersionFor(string $platform): ?string
    {
        $payload = $this->payloadFor($platform);

        $value = $payload['latest_version'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function minRequiredFor(string $platform): ?string
    {
        $payload = $this->payloadFor($platform);

        $value = $payload['minimum_supported_version'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function flush(): void
    {
        $this->memo = [];
        Cache::forget('mobile-versions:payload:ios');
        Cache::forget('mobile-versions:payload:android');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $platform): array
    {
        /** @var array{minimum_supported_version?: string, latest_version?: string, update_url?: string, force_update_below?: string} $cfg */
        $cfg = (array) config('mobile.' . $platform, []);

        $rows = MobileVersion::query()
            ->forPlatform($platform)
            ->get()
            ->keyBy('kind');

        $min = $rows->get(MobileVersion::KIND_MIN_REQUIRED);
        $latest = $rows->get(MobileVersion::KIND_LATEST);

        $minVersion = $min instanceof MobileVersion ? $min->version : ($cfg['minimum_supported_version'] ?? null);
        $latestVersion = $latest instanceof MobileVersion ? $latest->version : ($cfg['latest_version'] ?? null);
        $latestStoreUrl = $latest instanceof MobileVersion ? $latest->store_url : null;
        $minStoreUrl = $min instanceof MobileVersion ? $min->store_url : null;
        $updateUrl = $latestStoreUrl ?? $minStoreUrl ?? ($cfg['update_url'] ?? null);

        return [
            'platform' => $platform,
            'minimum_supported_version' => $minVersion,
            'latest_version' => $latestVersion,
            'update_url' => $updateUrl,
            'force_update_below' => $cfg['force_update_below'] ?? null,
        ];
    }
}
