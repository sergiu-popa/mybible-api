<?php

declare(strict_types=1);

namespace App\Domain\QrCode\Actions;

use App\Domain\QrCode\Models\QrCode;
use App\Domain\QrCode\Support\QrCodeCacheKeys;
use App\Http\Resources\QrCode\QrCodeListItemResource;
use App\Support\Caching\CachedRead;

final class ListQrCodesAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        return $this->cache->read(
            QrCodeCacheKeys::list(),
            QrCodeCacheKeys::tagsForQr(),
            86400,
            static function (): array {
                $qrCodes = QrCode::query()->orderBy('reference')->get();

                return QrCodeListItemResource::collection($qrCodes)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}
