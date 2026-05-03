<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Reconcile;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Validates that the polymorphic-shape migration evolves the Symfony
 * `resource_downloads` (originally `resource_download`) table in place:
 *
 * - `resource_id` renames to `downloadable_id`
 * - `downloadable_type` exists and is backfilled to
 *   `'educational_resource'` for existing legacy rows
 * - `ip_address` is dropped
 * - The three lookup indexes are present
 */
final class ResourceDownloadsMigrationTest extends ReconcileTestCase
{
    public function test_it_evolves_legacy_resource_downloads_into_polymorphic_shape(): void
    {
        $this->dropIfExists('resource_downloads');

        $this->recreateLegacyTable('resource_downloads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('resource_id');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::table('resource_downloads')->insert([
            ['resource_id' => 42, 'ip_address' => '10.0.0.1', 'created_at' => now()],
            ['resource_id' => 43, 'ip_address' => '10.0.0.2', 'created_at' => now()],
        ]);

        $this->runMigration('2026_05_03_003002_create_or_evolve_resource_downloads_for_polymorphic_shape.php');

        $this->assertColumnExists('resource_downloads', 'downloadable_id');
        $this->assertColumnExists('resource_downloads', 'downloadable_type');
        $this->assertColumnExists('resource_downloads', 'user_id');
        $this->assertColumnExists('resource_downloads', 'device_id');
        $this->assertColumnExists('resource_downloads', 'language');
        $this->assertColumnExists('resource_downloads', 'source');

        $this->assertFalse(
            Schema::hasColumn('resource_downloads', 'ip_address'),
            'Expected ip_address to be dropped after the migration.',
        );

        $this->assertSame(
            ['educational_resource', 'educational_resource'],
            DB::table('resource_downloads')->orderBy('id')->pluck('downloadable_type')->all(),
        );

        $this->assertSame(
            [42, 43],
            DB::table('resource_downloads')->orderBy('id')->pluck('downloadable_id')->map(static fn ($v) => (int) $v)->all(),
        );

        $expectedIndexes = [
            'resource_downloads_target_created_idx',
            'resource_downloads_user_created_idx',
            'resource_downloads_created_idx',
        ];
        $names = array_map(static fn (array $i): string => (string) ($i['name'] ?? ''), Schema::getIndexes('resource_downloads'));
        foreach ($expectedIndexes as $expected) {
            $this->assertContains($expected, $names, sprintf('Expected index "%s" on resource_downloads.', $expected));
        }
    }
}
