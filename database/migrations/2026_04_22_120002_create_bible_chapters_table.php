<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bible_chapters')) {
            return;
        }

        Schema::create('bible_chapters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bible_book_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->unsignedSmallInteger('verse_count');
            $table->timestamps();

            $table->unique(['bible_book_id', 'number']);
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bible_chapters');
    }
};
