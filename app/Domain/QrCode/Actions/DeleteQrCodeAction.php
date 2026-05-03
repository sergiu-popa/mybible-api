<?php

declare(strict_types=1);

namespace App\Domain\QrCode\Actions;

use App\Domain\QrCode\Models\QrCode;
use App\Domain\QrCode\Support\QrCodeCacheKeys;
use Illuminate\Support\Facades\Cache;

final class DeleteQrCodeAction
{
    public function handle(QrCode $qrCode): void
    {
        $qrCode->delete();

        Cache::tags(QrCodeCacheKeys::tagsForQr())->flush();
    }
}
