<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hymnal_favorites', function (Blueprint $table): void {
            $table->id();
            // Symfony users table uses int (not bigint) for its primary key,
            // so user_id has to match width — foreignId would create bigint and fail FK.
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('hymnal_song_id')
                ->constrained('hymnal_songs')
                ->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'hymnal_song_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hymnal_favorites');
    }
};
