<?php

declare(strict_types=1);

namespace App\Domain\QrCode\Actions;

use App\Domain\QrCode\DataTransferObjects\UpdateQrCodeData;
use App\Domain\QrCode\Models\QrCode;
use App\Domain\QrCode\Support\QrCodeCacheKeys;
use Illuminate\Support\Facades\Cache;

final class UpdateQrCodeAction
{
    public function handle(QrCode $qrCode, UpdateQrCodeData $data): QrCode
    {
        $attributes = [];

        if ($data->placeProvided && $data->place !== null) {
            $attributes['place'] = $data->place;
        }
        if ($data->baseUrlProvided) {
            $attributes['base_url'] = $data->baseUrl ?? '';
        }
        if ($data->sourceProvided && $data->source !== null) {
            $attributes['source'] = $data->source;
        }
        if ($data->destinationProvided && $data->destination !== null) {
            $attributes['destination'] = $data->destination;
            $attributes['url'] = $data->destination;
        }
        if ($data->nameProvided && $data->name !== null) {
            $attributes['name'] = $data->name;
        }
        if ($data->contentProvided && $data->content !== null) {
            $attributes['content'] = $data->content;
        }
        if ($data->descriptionProvided) {
            $attributes['description'] = $data->description;
        }
        if ($data->referenceProvided) {
            $attributes['reference'] = $data->reference;
        }
        if ($data->imagePathProvided) {
            $attributes['image_path'] = $data->imagePath;
        }

        if ($attributes !== []) {
            $qrCode->update($attributes);
        }

        Cache::tags(QrCodeCacheKeys::tagsForQr())->flush();

        return $qrCode->fresh() ?? $qrCode;
    }
}
