<?php

declare(strict_types=1);

namespace App\Http\Resources\EducationalResources;

use App\Domain\EducationalResources\Models\ResourceBook;
use Illuminate\Http\Request;

/**
 * @mixin ResourceBook
 */
final class ResourceBookDetailResource extends ResourceBookListResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'chapters' => ResourceBookChapterListItemResource::collection($this->chapters),
        ];
    }
}
