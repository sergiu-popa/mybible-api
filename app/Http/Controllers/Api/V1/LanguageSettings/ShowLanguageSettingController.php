<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\LanguageSettings;

use App\Domain\LanguageSettings\Actions\ShowPublicLanguageSettingAction;
use App\Http\Resources\LanguageSettings\PublicLanguageSettingResource;

final class ShowLanguageSettingController
{
    public function __invoke(
        string $language,
        ShowPublicLanguageSettingAction $action,
    ): PublicLanguageSettingResource {
        return PublicLanguageSettingResource::make($action->execute($language));
    }
}
