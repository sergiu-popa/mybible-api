<?php

declare(strict_types=1);

namespace App\Http\Resources\Verses;

use App\Domain\Bible\Models\BibleVerse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BibleVerse
 */
final class VerseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'version' => $this->version->abbreviation,
            'book' => $this->book->abbreviation,
            'chapter' => $this->chapter,
            'verse' => $this->verse,
            'text' => $this->text,
        ];
    }
}
