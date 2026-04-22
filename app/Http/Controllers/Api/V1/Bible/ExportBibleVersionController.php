<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Bible;

use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Bible\Support\BibleCacheHeaders;
use App\Domain\Bible\Support\BibleVersionExporter;
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
        BibleVersionExporter $exporter,
    ): Response {
        $headers = BibleCacheHeaders::forVersionExport($version);

        if ($request->headers->get('If-None-Match') === $headers['ETag']) {
            return response('', 304)->withHeaders($headers);
        }

        $stream = $exporter->stream($version);
        $stream->headers->add($headers);
        $stream->headers->set('Content-Type', 'application/json');

        return $stream;
    }
}
