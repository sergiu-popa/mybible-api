<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\DataTransferObjects\ResourceBookChapterData;
use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Models\ResourceBookChapter;
use App\Domain\EducationalResources\Support\ResourceBooksCacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final class CreateResourceBookChapterAction
{
    public function execute(ResourceBook $book, ResourceBookChapterData $data): ResourceBookChapter
    {
        $position = $data->position ?? $this->nextPosition($book);

        if ($data->position !== null) {
            $exists = ResourceBookChapter::query()
                ->where('resource_book_id', $book->id)
                ->where('position', $position)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'position' => ['Position is already taken within this book.'],
                ]);
            }
        }

        $chapter = ResourceBookChapter::create([
            'resource_book_id' => $book->id,
            'position' => $position,
            'title' => $data->title,
            'content' => $data->content,
            'audio_cdn_url' => $data->audioCdnUrl,
            'audio_embed' => $data->audioEmbed,
            'duration_seconds' => $data->durationSeconds,
        ]);

        Cache::tags(ResourceBooksCacheKeys::tagsForBook($book->id))->flush();

        return $chapter;
    }

    private function nextPosition(ResourceBook $book): int
    {
        $max = ResourceBookChapter::query()
            ->where('resource_book_id', $book->id)
            ->max('position');

        return ((int) $max) + 1;
    }
}
