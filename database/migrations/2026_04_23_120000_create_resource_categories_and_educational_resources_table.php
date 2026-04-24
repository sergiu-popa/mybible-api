<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fresh CI/dev databases build the target schema here. In the shared
        // prod database the Symfony `resource` table still exists; the
        // sibling reconciliation migration handles that path. Early-return
        // so we don't shadow Symfony's tables before reconciliation runs.
        if (Schema::hasTable('resource')) {
            return;
        }

        Schema::create('resource_categories', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('language', 3);
            $table->timestamps();

            $table->index('language');
        });

        Schema::create('educational_resources', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('resource_category_id')
                ->constrained('resource_categories')
                ->cascadeOnDelete();
            $table->string('type', 16);
            $table->json('title');
            $table->json('summary')->nullable();
            $table->json('content');
            $table->string('thumbnail_path', 255)->nullable();
            $table->string('media_path', 255)->nullable();
            $table->string('author', 255)->nullable();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index(['resource_category_id', 'type', 'published_at'], 'edu_res_cat_type_published_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('educational_resources');
        Schema::dropIfExists('resource_categories');
    }
};
