<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Bible;

use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Bible\Support\BibleCacheHeaders;
use App\Http\Requests\Bible\ListBibleVersionsRequest;
use App\Http\Resources\Bible\BibleVersionResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Bible
 */
final class ListBibleVersionsController
{
    public function __invoke(ListBibleVersionsRequest $request): Response
    {
        $query = BibleVersion::query()->orderBy('abbreviation');

        $language = $request->language();
        if ($language !== null) {
            $query->forLanguage($language);
        }

        $headers = BibleCacheHeaders::forVersionList($query);

        if ($request->headers->get('If-None-Match') === $headers['ETag']) {
            return response('', 304)->withHeaders($headers);
        }

        return BibleVersionResource::collection($query->paginate($request->perPage()))
            ->response($request)
            ->withHeaders($headers);
    }
}
