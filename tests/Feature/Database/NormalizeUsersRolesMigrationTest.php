<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class NormalizeUsersRolesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function insertUserWithRoles(string $rolesJson): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'roles probe',
            'email' => 'roles+' . uniqid() . '@example.test',
            'password' => 'unused',
            'roles' => $rolesJson,
            'is_super' => false,
            'languages' => json_encode([]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function rolesProvider(): iterable
    {
        yield 'role_admin uppercased' => ['["ROLE_ADMIN"]', ['admin']];
        yield 'role_editor uppercased' => ['["ROLE_EDITOR"]', ['admin']];
        yield 'lowercased editor' => ['["editor"]', ['admin']];
        yield 'lowercased admin stays admin' => ['["admin"]', ['admin']];
        yield 'admin and editor dedupe to single admin' => ['["ROLE_ADMIN","ROLE_EDITOR"]', ['admin']];
        yield 'unknown role passes through' => ['["custom_marker"]', ['custom_marker']];
        yield 'mix retains unknowns alongside admin' => ['["ROLE_EDITOR","custom"]', ['admin', 'custom']];
        yield 'empty roles stays empty' => ['[]', []];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('rolesProvider')]
    public function test_migration_collapses_legacy_role_values(string $startRoles, array $expected): void
    {
        $userId = $this->insertUserWithRoles($startRoles);

        $migration = require database_path(
            'migrations/2026_04_30_000006_normalize_users_roles.php',
        );
        $migration->up();

        $stored = DB::table('users')->where('id', $userId)->value('roles');

        $this->assertIsString($stored);
        $this->assertSame($expected, json_decode((string) $stored, true));
    }
}
