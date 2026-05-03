<?php

declare(strict_types=1);

namespace App\Http\Resources\EducationalResources;

use App\Domain\EducationalResources\Models\ResourceBookChapter;
use Illuminate\Http\Request;

/**
 * @mixin ResourceBookChapter
 */
final class AdminResourceBookChapterResource extends ResourceBookChapterResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'resource_book_id' => $this->resource_book_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
