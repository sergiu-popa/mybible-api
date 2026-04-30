<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\References;

use App\Domain\Reference\Parser\ReferenceParser;
use App\Http\Requests\Admin\References\ValidateReferenceRequest;
use Illuminate\Http\JsonResponse;

/**
 * Validation-only endpoint used by the admin's daily-verse and similar
 * reference inputs. Mirrors `ReferenceParser` exactly so admin and
 * storage agree on shape; throws `InvalidReferenceException` on bad
 * input, which the global handler renders as 422 with the `reference`
 * field highlighted.
 *
 * No side effects — does not touch the daily-verse table or any cache.
 */
final class ValidateReferenceController
{
    public function __invoke(
        ValidateReferenceRequest $request,
        ReferenceParser $parser,
    ): JsonResponse {
        $references = $parser->parse($request->reference());

        $payload = array_map(static fn ($reference): array => [
            'book' => $reference->book,
            'chapter' => $reference->chapter,
            'verses' => $reference->verses,
            'version' => $reference->version,
        ], $references);

        return response()->json([
            'valid' => true,
            'references' => $payload,
        ]);
    }
}
