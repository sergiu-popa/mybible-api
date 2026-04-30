<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class UserLanguagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_languages_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'languages'));
    }

    public function test_languages_defaults_to_empty_array(): void
    {
        $user = User::factory()->create();

        $this->assertSame([], $user->languages);
    }

    public function test_languages_factory_state_assigns_codes(): void
    {
        $user = User::factory()->withLanguages(['ro', 'hu'])->create();

        $this->assertSame(['ro', 'hu'], $user->languages);
    }

    public function test_languages_is_persisted_as_json_and_cast_back_to_array(): void
    {
        $user = User::factory()->withLanguages(['en', 'fr'])->create();

        $rawJson = DB::table('users')->where('id', $user->id)->value('languages');

        $this->assertIsString($rawJson);
        $this->assertSame(['en', 'fr'], json_decode((string) $rawJson, true));
        $this->assertSame(['en', 'fr'], $user->refresh()->languages);
    }

    public function test_legacy_language_column_remains_independent(): void
    {
        $user = User::factory()->create([
            'language' => 'rom',
            'languages' => ['ro'],
        ]);

        $this->assertSame('rom', $user->refresh()->language);
        $this->assertSame(['ro'], $user->refresh()->languages);
    }

    /**
     * @return iterable<string, array{string|null, list<string>}>
     */
    public static function legacyLanguageMappingProvider(): iterable
    {
        yield 'three-char rom maps to ro' => ['rom', ['ro']];
        yield 'three-char hun maps to hu' => ['hun', ['hu']];
        yield 'three-char spa maps to es' => ['spa', ['es']];
        yield 'three-char fra maps to fr' => ['fra', ['fr']];
        yield 'three-char ger maps to de' => ['ger', ['de']];
        yield 'three-char eng maps to en' => ['eng', ['en']];
        yield 'two-char ro stays ro' => ['ro', ['ro']];
        yield 'unknown code yields empty list' => ['xx', []];
        yield 'null language yields empty list' => [null, []];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('legacyLanguageMappingProvider')]
    public function test_migration_backfill_maps_legacy_language_to_languages(
        ?string $legacy,
        array $expected,
    ): void {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Backfill Probe',
            'email' => 'backfill+' . uniqid() . '@example.test',
            'password' => 'unused',
            'roles' => json_encode([]),
            'is_super' => false,
            'language' => $legacy,
            'languages' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->where('id', $userId)->update(['languages' => null]);

        $migration = require database_path(
            'migrations/2026_04_30_000001_add_languages_to_users_table.php',
        );
        $migration->up();

        $stored = DB::table('users')->where('id', $userId)->value('languages');

        $this->assertIsString($stored);
        $this->assertSame($expected, json_decode((string) $stored, true));
    }
}
