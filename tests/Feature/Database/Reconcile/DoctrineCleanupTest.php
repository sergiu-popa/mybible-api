<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Reconcile;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Assert;

final class DoctrineCleanupTest extends ReconcileTestCase
{
    use RefreshDatabase;

    public function test_it_drops_doctrine_table_and_idempotently_re_runs(): void
    {
        Schema::create('doctrine_migration_versions', function (Blueprint $table): void {
            $table->string('version')->primary();
            $table->timestamp('executed_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('salt')->nullable();
        });

        $this->assertTrue(Schema::hasTable('doctrine_migration_versions'));
        $this->assertTrue(Schema::hasColumn('users', 'salt'));

        $migration = $this->loadMigration('2026_05_03_000301_drop_doctrine_artefacts.php');
        Assert::assertTrue(method_exists($migration, 'up'));

        $migration->up();

        $this->assertFalse(Schema::hasTable('doctrine_migration_versions'));
        $this->assertFalse(Schema::hasColumn('users', 'salt'));

        // Idempotent — second run is a no-op.
        $migration->up();

        $this->assertFalse(Schema::hasTable('doctrine_migration_versions'));
    }
}
