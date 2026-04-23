<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hymnal_songs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hymnal_book_id')
                ->constrained('hymnal_books')
                ->cascadeOnDelete();
            $table->unsignedInteger('number')->nullable();
            $table->json('title');
            $table->json('author')->nullable();
            $table->json('composer')->nullable();
            $table->json('copyright')->nullable();
            $table->json('stanzas');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['hymnal_book_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hymnal_songs');
    }
};
