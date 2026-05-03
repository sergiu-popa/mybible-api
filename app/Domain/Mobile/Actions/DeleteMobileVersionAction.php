<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Actions;

use App\Domain\Mobile\Models\MobileVersion;
use App\Domain\Mobile\Support\MobileVersionsRepository;

final class DeleteMobileVersionAction
{
    public function __construct(private readonly MobileVersionsRepository $repository) {}

    public function handle(MobileVersion $version): void
    {
        $version->delete();
        $this->repository->flush();
    }
}
