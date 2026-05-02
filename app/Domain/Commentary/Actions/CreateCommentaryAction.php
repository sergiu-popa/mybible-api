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
            : $this->autoSlug($data->abbreviation, $data->language);

        return Commentary::create([
            'slug' => $slug,
            'name' => $data->name,
            'abbreviation' => $data->abbreviation,
            'language' => $data->language,
            'is_published' => false,
            'source_commentary_id' => $data->sourceCommentaryId,
        ]);
    }

    private function autoSlug(string $abbreviation, string $language): string
    {
        $base = Str::slug(strtolower($abbreviation));

        if ($base === '') {
            $base = 'commentary';
        }

        $candidate = $base;

        if (! Commentary::where('slug', $candidate)->exists()) {
            return $candidate;
        }

        $candidate = $base . '-' . $language;

        $suffix = 2;
        while (Commentary::where('slug', $candidate)->exists()) {
            $candidate = $base . '-' . $language . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
