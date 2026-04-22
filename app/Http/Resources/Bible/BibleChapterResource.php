<?php

declare(strict_types=1);

namespace App\Http\Resources\Bible;

use App\Domain\Bible\Models\BibleChapter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BibleChapter
 */
final class BibleChapterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'verse_count' => $this->verse_count,
        ];
    }
}
