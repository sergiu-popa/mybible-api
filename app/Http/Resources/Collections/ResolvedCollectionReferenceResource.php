<?php

declare(strict_types=1);

namespace App\Http\Resources\Collections;

use App\Domain\Collections\DataTransferObjects\ResolvedCollectionReference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ResolvedCollectionReference
 */
final class ResolvedCollectionReferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'raw' => $this->raw,
            'parsed' => $this->parsed,
            'display_text' => $this->displayText,
            'parse_error' => $this->parseError,
        ];
    }
}
