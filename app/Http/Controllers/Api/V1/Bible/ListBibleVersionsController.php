<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Bible;

use App\Domain\Bible\Actions\ListBibleVersionsAction;
use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Bible\Support\BibleCacheHeaders;
use App\Http\Requests\Bible\ListBibleVersionsRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Bible
 */
final class ListBibleVersionsController
{
    public function __invoke(
        ListBibleVersionsRequest $request,
        ListBibleVersionsAction $action,
    ): Response {
        // ETag is computed off the underlying versions table (max(updated_at)
        // + count) so Cloudflare/clients short-circuit before the application
        // cache. Same query the cache build closure runs internally.
        $query = BibleVersion::query()->orderBy('abbreviation');
        $language = $request->language();
        if ($language !== null) {
            $query->forLanguage($language);
        }

        $headers = BibleCacheHeaders::forVersionList($query);

        if ($request->headers->get('If-None-Match') === $headers['ETag']) {
            return response('', 304)->withHeaders($headers);
        }

        $page = max(1, (int) $request->query('page', '1'));

        $payload = $action->execute($language, $page, $request->perPage());

        $response = new JsonResponse($payload);
        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
