<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\LanguageSettings;

use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Commentary\Models\Commentary;
use App\Domain\Devotional\Models\DevotionalType;
use App\Domain\LanguageSettings\Models\LanguageSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class LanguageSettingsEndpointsTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpApiKeyClient();
    }

    private function actingAsSuper(): User
    {
        $user = User::factory()->super()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    public function test_admin_index_returns_seeded_languages(): void
    {
        $this->actingAsSuper();

        $this->getJson(route('admin.language-settings.index'))
            ->assertOk()
            ->assertJsonStructure(['data' => [['language', 'default_bible_version', 'default_commentary', 'default_devotional_type']]])
            ->assertJsonFragment(['language' => 'ro'])
            ->assertJsonFragment(['language' => 'en'])
            ->assertJsonFragment(['language' => 'es']);
    }

    public function test_admin_index_is_blocked_for_non_super_admin(): void
    {
        $user = User::factory()->admin()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('admin.language-settings.index'))
            ->assertForbidden();
    }

    public function test_admin_index_requires_authentication(): void
    {
        $this->getJson(route('admin.language-settings.index'))
            ->assertUnauthorized();
    }

    public function test_admin_patch_persists_default_bible_version(): void
    {
        $this->actingAsSuper();
        $version = BibleVersion::factory()->create(['abbreviation' => 'KJV']);

        $this->patchJson(route('admin.language-settings.update', ['language' => 'en']), [
            'default_bible_version_abbreviation' => 'KJV',
        ])
            ->assertOk()
            ->assertJsonPath('data.default_bible_version.abbreviation', 'KJV');

        $row = LanguageSetting::query()->where('language', 'en')->first();
        self::assertNotNull($row);
        self::assertSame($version->id, $row->default_bible_version_id);
    }

    public function test_admin_patch_persists_commentary_and_devotional_type(): void
    {
        $this->actingAsSuper();
        $commentary = Commentary::factory()->create();
        $type = DevotionalType::factory()->create();

        $this->patchJson(route('admin.language-settings.update', ['language' => 'ro']), [
            'default_commentary_id' => $commentary->id,
            'default_devotional_type_id' => $type->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.default_commentary.id', $commentary->id)
            ->assertJsonPath('data.default_devotional_type.id', $type->id);
    }

    public function test_admin_patch_validates_unknown_bible_version(): void
    {
        $this->actingAsSuper();

        $this->patchJson(route('admin.language-settings.update', ['language' => 'en']), [
            'default_bible_version_abbreviation' => 'NOPE',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['default_bible_version_abbreviation']);
    }

    public function test_public_show_returns_slug_only_payload(): void
    {
        $version = BibleVersion::factory()->create(['abbreviation' => 'KJV']);
        $commentary = Commentary::factory()->create();
        LanguageSetting::query()->where('language', 'en')->update([
            'default_bible_version_id' => $version->id,
            'default_commentary_id' => $commentary->id,
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('language-settings.show', ['language' => 'en']))
            ->assertOk()
            ->assertJsonPath('data.language', 'en')
            ->assertJsonPath('data.default_bible_version.abbreviation', 'KJV')
            ->assertJsonPath('data.default_commentary.slug', $commentary->slug)
            ->assertJsonMissingPath('data.default_devotional_type')
            ->assertJsonMissingPath('data.default_bible_version.id');
    }

    public function test_public_show_404_for_uppercase_path(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson('/api/v1/language-settings/EN')
            ->assertNotFound();
    }
}
