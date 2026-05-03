<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('collections')) {
            Schema::create('collections', function (Blueprint $table): void {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->char('language', 2);
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->index(['language', 'position']);
            });
        } else {
            Schema::table('collections', function (Blueprint $table): void {
                if (! Schema::hasColumn('collections', 'slug')) {
                    $table->string('slug')->after('id');
                }
                if (! Schema::hasColumn('collections', 'name')) {
                    $table->string('name')->after('slug');
                }
                if (! Schema::hasColumn('collections', 'language')) {
                    $table->char('language', 2)->after('name');
                }
                if (! Schema::hasColumn('collections', 'position')) {
                    $table->unsignedInteger('position')->default(0)->after('language');
                }
                if (! Schema::hasColumn('collections', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (! Schema::hasColumn('collections', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });

            $indexes = collect(Schema::getIndexes('collections'));

            if (! $indexes->contains(fn (array $idx): bool => ($idx['unique'] ?? false) && $idx['columns'] === ['slug'])) {
                Schema::table('collections', function (Blueprint $table): void {
                    $table->unique('slug');
                });
            }

            if (! $indexes->contains(fn (array $idx): bool => $idx['columns'] === ['language', 'position'])) {
                Schema::table('collections', function (Blueprint $table): void {
                    $table->index(['language', 'position']);
                });
            }
        }

        if (Schema::hasTable('collection_topics')) {
            Schema::table('collection_topics', function (Blueprint $table): void {
                if (! Schema::hasColumn('collection_topics', 'collection_id')) {
                    $table->foreignId('collection_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('collections')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('collection_topics', 'image_cdn_url')) {
                    $table->text('image_cdn_url')->nullable()->after('description');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('collection_topics')) {
            Schema::table('collection_topics', function (Blueprint $table): void {
                if (Schema::hasColumn('collection_topics', 'collection_id')) {
                    $table->dropConstrainedForeignId('collection_id');
                }
                if (Schema::hasColumn('collection_topics', 'image_cdn_url')) {
                    $table->dropColumn('image_cdn_url');
                }
            });
        }

        Schema::dropIfExists('collections');
    }
};
