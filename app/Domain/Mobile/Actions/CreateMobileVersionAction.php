<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Actions;

use App\Domain\Mobile\DataTransferObjects\CreateMobileVersionData;
use App\Domain\Mobile\Models\MobileVersion;
use App\Domain\Mobile\Support\MobileVersionsRepository;

final class CreateMobileVersionAction
{
    public function __construct(private readonly MobileVersionsRepository $repository) {}

    public function handle(CreateMobileVersionData $data): MobileVersion
    {
        $version = MobileVersion::query()->create([
            'platform' => $data->platform,
            'kind' => $data->kind,
            'version' => $data->version,
            'released_at' => $data->releasedAt,
            'release_notes' => $data->releaseNotes,
            'store_url' => $data->storeUrl,
        ]);

        $this->repository->flush();

        return $version;
    }
}
