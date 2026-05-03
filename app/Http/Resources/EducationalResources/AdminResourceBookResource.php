<?php

declare(strict_types=1);

namespace App\Http\Resources\EducationalResources;

use App\Domain\EducationalResources\Models\ResourceBook;
use Illuminate\Http\Request;

/**
 * @mixin ResourceBook
 */
final class AdminResourceBookResource extends ResourceBookListResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            ...parent::toArray($request),
            'is_published' => $this->is_published,
            'position' => $this->position,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
