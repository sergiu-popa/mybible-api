<?php

declare(strict_types=1);

namespace App\Http\Resources\QrCode;

use App\Domain\QrCode\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QrCode
 */
final class QrCodeResource extends JsonResource
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
            'base_url' => $this->base_url,
            'source' => $this->source,
            'destination' => $this->destination !== '' ? $this->destination : $this->url,
            'name' => $this->name,
            'content' => $this->content,
            'description' => $this->description,
            'url' => $this->destination !== '' ? $this->destination : $this->url,
            'image_url' => $this->imageUrl(),
        ];
    }
}
