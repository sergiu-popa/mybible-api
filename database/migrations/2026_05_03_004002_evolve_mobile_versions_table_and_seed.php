<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mobile_versions')) {
            Schema::create('mobile_versions', function (Blueprint $table): void {
                $table->id();
                $table->string('platform', 16);
                $table->string('kind', 16);
                $table->string('version', 25);
                $table->timestamp('released_at')->nullable();
                $table->json('release_notes')->nullable();
                $table->string('store_url')->nullable();
                $table->timestamps();

                $table->unique(['platform', 'kind'], 'mobile_versions_platform_kind_unique');
            });
        } else {
            Schema::table('mobile_versions', function (Blueprint $table): void {
                if (! Schema::hasColumn('mobile_versions', 'platform')) {
                    $table->string('platform', 16)->after('id');
                }
                if (! Schema::hasColumn('mobile_versions', 'kind')) {
                    $table->string('kind', 16)->after('platform');
                }
                if (! Schema::hasColumn('mobile_versions', 'version')) {
                    $table->string('version', 25)->after('kind');
                }
                if (! Schema::hasColumn('mobile_versions', 'released_at')) {
                    $table->timestamp('released_at')->nullable()->after('version');
                }
                if (! Schema::hasColumn('mobile_versions', 'release_notes')) {
                    $table->json('release_notes')->nullable()->after('released_at');
                }
                if (! Schema::hasColumn('mobile_versions', 'store_url')) {
                    $table->string('store_url')->nullable()->after('release_notes');
                }
                if (! Schema::hasColumn('mobile_versions', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (! Schema::hasColumn('mobile_versions', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });

            $hasUnique = collect(Schema::getIndexes('mobile_versions'))
                ->contains(fn (array $idx): bool => ($idx['unique'] ?? false)
                    && $idx['columns'] === ['platform', 'kind'],
                );

            if (! $hasUnique) {
                Schema::table('mobile_versions', function (Blueprint $table): void {
                    $table->unique(['platform', 'kind'], 'mobile_versions_platform_kind_unique');
                });
            }
        }

        // One-shot seed from config when the table is empty so the existing
        // GET /mobile/version endpoint shape is preserved end-to-end.
        if (DB::table('mobile_versions')->count() !== 0) {
            return;
        }

        $now = now();
        $rows = [];

        foreach (['ios', 'android'] as $platform) {
            $cfg = (array) config('mobile.' . $platform, []);

            $minVersion = $cfg['minimum_supported_version'] ?? null;
            $latestVersion = $cfg['latest_version'] ?? null;
            $storeUrl = $cfg['update_url'] ?? null;

            if (is_string($minVersion) && $minVersion !== '') {
                $rows[] = [
                    'platform' => $platform,
                    'kind' => 'min_required',
                    'version' => $minVersion,
                    'released_at' => null,
                    'release_notes' => null,
                    'store_url' => is_string($storeUrl) && $storeUrl !== '' ? $storeUrl : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (is_string($latestVersion) && $latestVersion !== '') {
                $rows[] = [
                    'platform' => $platform,
                    'kind' => 'latest',
                    'version' => $latestVersion,
                    'released_at' => null,
                    'release_notes' => null,
                    'store_url' => is_string($storeUrl) && $storeUrl !== '' ? $storeUrl : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('mobile_versions')->insert($rows);
        }
    }

    public function down(): void
    {
        // No-op: irreversible (would require recreating Symfony shape).
    }
};
