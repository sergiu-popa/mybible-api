<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\LanguageSettings;

use App\Domain\LanguageSettings\Actions\ListLanguageSettingsAction;
use App\Http\Resources\LanguageSettings\LanguageSettingResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListLanguageSettingsController
{
    public function __invoke(ListLanguageSettingsAction $action): AnonymousResourceCollection
    {
        return LanguageSettingResource::collection($action->execute());
    }
}
