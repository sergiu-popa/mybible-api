<?php

declare(strict_types=1);

namespace App\Domain\QrCode\Actions;

use App\Domain\QrCode\Models\QrCode;
use App\Domain\QrCode\Support\QrCodeCacheKeys;
use App\Http\Resources\QrCode\QrCodeResource;
use App\Support\Caching\CachedRead;

final class ShowQrCodeByReferenceAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $canonicalReference): array
    {
        return $this->cache->read(
            QrCodeCacheKeys::show($canonicalReference),
            QrCodeCacheKeys::tagsForQr(),
            86400,
            static function () use ($canonicalReference): array {
                $qrCode = QrCode::query()
                    ->forReference($canonicalReference)
                    ->firstOrFail();

                return QrCodeResource::make($qrCode)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}
