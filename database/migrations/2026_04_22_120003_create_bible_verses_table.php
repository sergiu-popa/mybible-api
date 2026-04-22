<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bible_verses')) {
            return;
        }

        Schema::create('bible_verses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bible_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bible_book_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('chapter');
            $table->unsignedSmallInteger('verse');
            $table->text('text');
            $table->timestamps();

            $table->index(
                ['bible_version_id', 'bible_book_id', 'chapter', 'verse'],
                'bible_verses_version_book_ch_v_idx',
            );
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bible_verses');
    }
};
