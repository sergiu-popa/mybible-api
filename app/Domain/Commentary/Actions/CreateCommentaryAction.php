<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Actions;

use App\Domain\Commentary\DataTransferObjects\CommentaryData;
use App\Domain\Commentary\Models\Commentary;
use Illuminate\Support\Str;

final class CreateCommentaryAction
{
    public function execute(CommentaryData $data): Commentary
    {
        $slug = $data->slug !== null && $data->slug !== ''
            ? $data->slug
            : Str::slug(strtolower($data->abbreviation));

        return Commentary::create([
            'slug' => $slug,
            'name' => $data->name,
            'abbreviation' => $data->abbreviation,
            'language' => $data->language,
            'is_published' => false,
            'source_commentary_id' => $data->sourceCommentaryId,
        ]);
    }
}
