<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_topics', function (Blueprint $table): void {
            $table->id();
            $table->string('language', 2);
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index('language');
            $table->index(['language', 'position']);
        });

        Schema::create('collection_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collection_topic_id')
                ->constrained('collection_topics')
                ->cascadeOnDelete();
            $table->string('reference');
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index(['collection_topic_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_references');
        Schema::dropIfExists('collection_topics');
    }
};
