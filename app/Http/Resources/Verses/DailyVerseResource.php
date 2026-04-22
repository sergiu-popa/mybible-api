<?php

declare(strict_types=1);

namespace App\Http\Resources\Verses;

use App\Domain\Verses\Models\DailyVerse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DailyVerse
 */
final class DailyVerseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->for_date->format('Y-m-d'),
            'reference' => $this->reference,
            'image_url' => $this->image_cdn_url,
        ];
    }
}
