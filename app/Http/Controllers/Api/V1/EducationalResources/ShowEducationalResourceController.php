<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\EducationalResources;

use App\Domain\EducationalResources\Models\EducationalResource;
use App\Http\Requests\EducationalResources\ShowEducationalResourceRequest;
use App\Http\Resources\EducationalResources\EducationalResourceDetailResource;

/**
 * @tags Educational Resources
 */
final class ShowEducationalResourceController
{
    /**
     * Show an educational resource.
     *
     * Resolves the resource by UUID (route-model binding uses the `uuid`
     * column). Eager-loads the parent category so the detail resource can
     * embed the nested category mini-object without an N+1.
     */
    public function __invoke(
        ShowEducationalResourceRequest $request,
        EducationalResource $resource,
    ): EducationalResourceDetailResource {
        $resource->load('category');

        return EducationalResourceDetailResource::make($resource);
    }
}
