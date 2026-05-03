<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\ResourceDownload;
use App\Domain\EducationalResources\Models\EducationalResource;
use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Models\ResourceBookChapter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceDownload>
 */
final class ResourceDownloadFactory extends Factory
{
    protected $model = ResourceDownload::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'downloadable_type' => ResourceDownload::TYPE_EDUCATIONAL_RESOURCE,
            'downloadable_id' => EducationalResource::factory(),
            'user_id' => null,
            'device_id' => fake()->uuid(),
            'language' => 'ro',
            'source' => fake()->randomElement(['ios', 'android', 'web']),
        ];
    }

    public function forResource(EducationalResource $resource): self
    {
        return $this->state(fn (): array => [
            'downloadable_type' => ResourceDownload::TYPE_EDUCATIONAL_RESOURCE,
            'downloadable_id' => $resource->id,
        ]);
    }

    public function forBook(ResourceBook $book): self
    {
        return $this->state(fn (): array => [
            'downloadable_type' => ResourceDownload::TYPE_RESOURCE_BOOK,
            'downloadable_id' => $book->id,
        ]);
    }

    public function forChapter(ResourceBookChapter $chapter): self
    {
        return $this->state(fn (): array => [
            'downloadable_type' => ResourceDownload::TYPE_RESOURCE_BOOK_CHAPTER,
            'downloadable_id' => $chapter->id,
        ]);
    }
}
