<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\AI\Support;

use App\Domain\AI\Support\AddReferencesVersionResolver;
use App\Domain\Bible\Models\BibleVersion;
use App\Domain\LanguageSettings\Models\LanguageSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class AddReferencesVersionResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_input_wins(): void
    {
        BibleVersion::factory()->create(['abbreviation' => 'KJV']);
        BibleVersion::factory()->romanian()->create();

        self::assertSame('KJV', (new AddReferencesVersionResolver)->resolve('en', 'KJV'));
    }

    public function test_falls_back_to_language_setting_default(): void
    {
        $configured = BibleVersion::factory()->create(['abbreviation' => 'NIV']);
        BibleVersion::factory()->romanian()->create();

        // Seeded row already exists for `en`; update it rather than insert.
        LanguageSetting::query()
            ->where('language', 'en')
            ->update(['default_bible_version_id' => $configured->id]);

        self::assertSame('NIV', (new AddReferencesVersionResolver)->resolve('en', null));
    }

    public function test_falls_back_to_config_when_setting_is_unset(): void
    {
        BibleVersion::factory()->romanian()->create();

        self::assertSame('VDC', (new AddReferencesVersionResolver)->resolve('en', null));
    }

    public function test_throws_when_resolved_version_does_not_exist(): void
    {
        $this->expectException(RuntimeException::class);

        (new AddReferencesVersionResolver)->resolve('en', 'NOT_A_REAL_ABBR');
    }
}
