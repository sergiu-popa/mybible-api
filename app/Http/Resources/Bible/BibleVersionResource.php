<?php

declare(strict_types=1);

namespace App\Http\Resources\Bible;

use App\Domain\Bible\Models\BibleVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BibleVersion
 */
final class BibleVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'abbreviation' => $this->abbreviation,
            'language' => $this->language,
        ];
    }
}
