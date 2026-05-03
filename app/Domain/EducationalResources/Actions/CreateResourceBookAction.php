<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\DataTransferObjects\ResourceBookData;
use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Support\ResourceBooksCacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class CreateResourceBookAction
{
    public function execute(ResourceBookData $data): ResourceBook
    {
        $slug = $data->slug !== null && $data->slug !== ''
            ? $data->slug
            : $this->autoSlug($data->name);

        $book = ResourceBook::create([
            'slug' => $slug,
            'name' => $data->name,
            'language' => $data->language,
            'description' => $data->description,
            'cover_image_url' => $data->coverImageUrl,
            'author' => $data->author,
            'is_published' => false,
            'position' => 0,
        ]);

        Cache::tags(ResourceBooksCacheKeys::tagsForList())->flush();

        return $book;
    }

    private function autoSlug(string $name): string
    {
        $base = Str::slug(mb_strtolower($name));

        if ($base === '') {
            $base = 'resource-book';
        }

        $candidate = $base;
        $suffix = 2;
        while (ResourceBook::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
