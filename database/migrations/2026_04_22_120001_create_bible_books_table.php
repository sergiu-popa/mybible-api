<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bible_books')) {
            return;
        }

        Schema::create('bible_books', function (Blueprint $table): void {
            $table->id();
            $table->string('abbreviation', 8)->unique();
            $table->string('testament', 8);
            $table->unsignedSmallInteger('position')->unique();
            $table->unsignedSmallInteger('chapter_count');
            $table->json('names');
            $table->json('short_names');
            $table->timestamps();

            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bible_books');
    }
};
