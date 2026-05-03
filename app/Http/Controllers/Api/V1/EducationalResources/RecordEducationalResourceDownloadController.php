<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\EducationalResources;

use App\Domain\Analytics\Actions\RecordResourceDownloadAction;
use App\Domain\Analytics\Support\ClientContextResolver;
use App\Domain\EducationalResources\Models\EducationalResource;
use App\Http\Requests\EducationalResources\RecordEducationalResourceDownloadRequest;
use Illuminate\Http\Response;

final class RecordEducationalResourceDownloadController
{
    public function __invoke(
        RecordEducationalResourceDownloadRequest $request,
        EducationalResource $resource,
        RecordResourceDownloadAction $action,
    ): Response {
        $context = ClientContextResolver::fromRequest($request);

        $action->execute($resource, $context, 'resource.downloaded');

        return response()->noContent();
    }
}
