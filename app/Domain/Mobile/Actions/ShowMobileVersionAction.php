<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Actions;

use App\Domain\Mobile\Support\MobileVersionsRepository;

final class ShowMobileVersionAction
{
    public function __construct(private readonly MobileVersionsRepository $repository) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(string $platform): array
    {
        return $this->repository->payloadFor($platform);
    }
}
