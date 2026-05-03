<?php

declare(strict_types=1);

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Models\CollectionTopic;

final class DeleteCollectionTopicAction
{
    public function handle(CollectionTopic $topic): void
    {
        $topic->delete();
    }
}
