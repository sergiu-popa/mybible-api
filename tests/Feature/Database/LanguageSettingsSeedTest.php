<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Domain\LanguageSettings\Models\LanguageSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LanguageSettingsSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_creates_one_row_per_supported_iso2_language(): void
    {
        $languages = LanguageSetting::query()->pluck('language')->all();

        self::assertEqualsCanonicalizing(
            ['ro', 'en', 'hu', 'es', 'fr', 'de', 'it'],
            $languages,
        );

        foreach ($languages as $language) {
            $row = LanguageSetting::query()->where('language', $language)->first();
            self::assertNotNull($row);
            self::assertNull($row->default_bible_version_id);
            self::assertNull($row->default_commentary_id);
            self::assertNull($row->default_devotional_type_id);
        }
    }
}
