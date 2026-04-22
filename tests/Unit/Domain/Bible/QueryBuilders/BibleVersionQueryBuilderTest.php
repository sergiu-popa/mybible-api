<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bible\QueryBuilders;

use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BibleVersionQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_language_filters_by_language_column(): void
    {
        $english = BibleVersion::factory()->create(['language' => Language::En->value]);
        $romanian = BibleVersion::factory()->romanian()->create();
        BibleVersion::factory()->create(['language' => Language::Hu->value]);

        $ids = BibleVersion::query()->forLanguage(Language::Ro)->pluck('id')->all();

        $this->assertSame([$romanian->id], $ids);
        $this->assertNotContains($english->id, $ids);
    }
}
