<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Actions;

use App\Domain\Commentary\Models\Commentary;

final class SetCommentaryPublicationAction
{
    public function execute(Commentary $commentary, bool $published): Commentary
    {
        $commentary->is_published = $published;
        $commentary->save();

        return $commentary;
    }
}
