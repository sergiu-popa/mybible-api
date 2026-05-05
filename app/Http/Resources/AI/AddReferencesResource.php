<?php

declare(strict_types=1);

namespace App\Http\Resources\AI;

use App\Domain\AI\DataTransferObjects\AddReferencesOutput;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AddReferencesOutput $resource
 */
final class AddReferencesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $output = $this->resource;

        return [
            'html' => $output->html,
            'references_added' => $output->referencesAdded,
            'prompt_version' => $output->promptVersion,
            'ai_call_id' => $output->aiCallId,
        ];
    }
}
