<?php

declare(strict_types=1);

namespace App\Domain\QrCode\Actions;

use App\Domain\QrCode\DataTransferObjects\CreateQrCodeData;
use App\Domain\QrCode\Models\QrCode;
use App\Domain\QrCode\Support\QrCodeCacheKeys;
use Illuminate\Support\Facades\Cache;

final class CreateQrCodeAction
{
    public function handle(CreateQrCodeData $data): QrCode
    {
        $qrCode = QrCode::query()->create([
            'place' => $data->place,
            'base_url' => $data->baseUrl ?? '',
            'source' => $data->source,
            'destination' => $data->destination,
            'url' => $data->destination,
            'name' => $data->name,
            'content' => $data->content,
            'description' => $data->description,
            'reference' => $data->reference,
            'image_path' => $data->imagePath,
        ]);

        Cache::tags(QrCodeCacheKeys::tagsForQr())->flush();

        return $qrCode;
    }
}
