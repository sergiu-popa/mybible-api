<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\LanguageSettings;

use App\Domain\LanguageSettings\Actions\UpdateLanguageSettingAction;
use App\Domain\LanguageSettings\Models\LanguageSetting;
use App\Http\Requests\Admin\LanguageSettings\UpdateLanguageSettingRequest;
use App\Http\Resources\LanguageSettings\LanguageSettingResource;

final class UpdateLanguageSettingController
{
    public function __invoke(
        UpdateLanguageSettingRequest $request,
        LanguageSetting $language,
        UpdateLanguageSettingAction $action,
    ): LanguageSettingResource {
        return LanguageSettingResource::make($action->execute($language, $request->toData($language)));
    }
}
