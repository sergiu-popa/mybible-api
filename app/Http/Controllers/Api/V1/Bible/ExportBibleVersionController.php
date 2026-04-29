<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Bible;

use App\Domain\Bible\Actions\ExportBibleVersionAction;
use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Bible\Support\BibleCacheHeaders;
use App\Http\Requests\Bible\ExportBibleVersionRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Bible
 */
final class ExportBibleVersionController
{
    public function __invoke(
        ExportBibleVersionRequest $request,
        BibleVersion $version,
        ExportBibleVersionAction $action,
    ): Response {
        $headers = BibleCacheHeaders::forVersionExport($version);

        if ($request->headers->get('If-None-Match') === $headers['ETag']) {
            return response('', 304)->withHeaders($headers);
        }

        $body = $action->execute($version);

        return response($body)
            ->withHeaders($headers)
            ->header('Content-Type', 'application/json');
    }
}
