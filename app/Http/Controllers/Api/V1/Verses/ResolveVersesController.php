<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Verses;

use App\Domain\Reference\Parser\ReferenceParser;
use App\Domain\Verses\Actions\ResolveVersesAction;
use App\Http\Requests\Verses\ResolveVersesRequest;
use App\Http\Resources\Verses\VerseCollection;

/**
 * @tags Verses
 */
final class ResolveVersesController
{
    public function __invoke(
        ResolveVersesRequest $request,
        ReferenceParser $parser,
        ResolveVersesAction $action,
    ): VerseCollection {
        $data = $request->toData($parser);

        $result = $action->handle($data);

        return (new VerseCollection($result->verses))
            ->additional(['meta' => ['missing' => $result->missing]]);
    }
}
