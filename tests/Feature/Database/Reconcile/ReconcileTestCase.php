<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Reconcile;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Shared scaffolding for reconcile migration feature tests. Each test
 * subclass:
 *
 * 1. Drops the Laravel-shape table the create migration laid down.
 * 2. Recreates the table under its Symfony name (legacy shape).
 * 3. Seeds rows.
 * 4. Invokes `(new $migration)->up()`.
 * 5. Asserts the post-migration shape (rename, column changes,
 *    UNIQUE constraints).
 *
 * `RefreshDatabase` is intentionally NOT used here — we want the
 * Laravel-shape schema as the starting point so we can simulate the
 * "Symfony left this around" prod scenario by dropping then recreating.
 */
abstract class ReconcileTestCase extends TestCase
{
    protected function loadMigration(string $relativePath): object
    {
        $migration = require database_path('migrations/' . $relativePath);

        if (! is_object($migration)) {
            throw new \RuntimeException('Migration file did not return an object: ' . $relativePath);
        }

        return $migration;
    }

    /**
     * Loads a migration and invokes `up()` on it. Centralises the call
     * so individual tests don't trip phpstan on the `Migration::up()`
     * lookup (the method is defined on the anonymous subclass).
     */
    protected function runMigration(string $relativePath): void
    {
        $migration = $this->loadMigration($relativePath);

        if (! method_exists($migration, 'up')) {
            throw new \RuntimeException('Migration is missing up(): ' . $relativePath);
        }

        $migration->up();
    }

    protected function dropIfExists(string $table): void
    {
        Schema::dropIfExists($table);
    }

    /**
     * @param  Closure(Blueprint): void  $shape
     */
    protected function recreateLegacyTable(string $table, Closure $shape): void
    {
        Schema::dropIfExists($table);

        Schema::create($table, $shape);
    }

    protected function assertTableExists(string $table): void
    {
        $this->assertTrue(Schema::hasTable($table), sprintf('Expected table "%s" to exist.', $table));
    }

    protected function assertTableMissing(string $table): void
    {
        $this->assertFalse(Schema::hasTable($table), sprintf('Expected table "%s" to be absent.', $table));
    }

    protected function assertColumnExists(string $table, string $column): void
    {
        $this->assertTrue(Schema::hasColumn($table, $column), sprintf('Expected column "%s.%s" to exist.', $table, $column));
    }
}
