<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Imports;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Http\Resources\Admin\Imports\ImportJobResource;

final class ShowImportJobController
{
    /**
     * Polling endpoint for any long-running admin import (Bible catalog,
     * commentary, etc.). Returns a uniform shape — `status`, `progress`,
     * `error`, `payload` — so the admin renders one progress widget
     * regardless of which capability owns the job.
     */
    public function __invoke(ImportJob $job): ImportJobResource
    {
        return ImportJobResource::make($job);
    }
}
