<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Actions;

use App\Domain\Mobile\DataTransferObjects\UpdateMobileVersionData;
use App\Domain\Mobile\Models\MobileVersion;
use App\Domain\Mobile\Support\MobileVersionsRepository;

final class UpdateMobileVersionAction
{
    public function __construct(private readonly MobileVersionsRepository $repository) {}

    public function handle(MobileVersion $version, UpdateMobileVersionData $data): MobileVersion
    {
        $attributes = [];

        if ($data->platformProvided && $data->platform !== null) {
            $attributes['platform'] = $data->platform;
        }
        if ($data->kindProvided && $data->kind !== null) {
            $attributes['kind'] = $data->kind;
        }
        if ($data->versionProvided && $data->version !== null) {
            $attributes['version'] = $data->version;
        }
        if ($data->releasedAtProvided) {
            $attributes['released_at'] = $data->releasedAt;
        }
        if ($data->releaseNotesProvided) {
            $attributes['release_notes'] = $data->releaseNotes;
        }
        if ($data->storeUrlProvided) {
            $attributes['store_url'] = $data->storeUrl;
        }

        if ($attributes !== []) {
            $version->update($attributes);
        }

        $this->repository->flush();

        return $version->fresh() ?? $version;
    }
}
