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
            'reference' => $this->reference,
            'url' => $this->url,
            'image_url' => $this->imageUrl(),
        ];
    }
}
