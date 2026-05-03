<?php

declare(strict_types=1);

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Models\Collection;

final class DeleteCollectionAction
{
    /**
     * Topics belonging to this collection are orphaned (FK SET NULL); they
     * remain browsable individually.
     */
    public function handle(Collection $collection): void
    {
        $collection->delete();
    }
}
