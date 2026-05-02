<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Actions;

use App\Domain\Commentary\Models\Commentary;

final class UpdateCommentaryAction
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function execute(Commentary $commentary, array $changes): Commentary
    {
        $commentary->fill($changes)->save();

        return $commentary;
    }
}
