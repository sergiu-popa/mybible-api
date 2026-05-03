<?php

declare(strict_types=1);

namespace App\Http\Resources\QrCode;

use App\Domain\QrCode\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QrCode
 */
final class QrCodeListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'place' => $this->place,
            'source' => $this->source,
            'destination' => $this->destination !== '' ? $this->destination : $this->url,
            'name' => $this->name,
            'url' => $this->destination !== '' ? $this->destination : $this->url,
            'image_url' => $this->imageUrl(),
        ];
    }
}
