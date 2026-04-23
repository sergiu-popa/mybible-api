<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('devotional_favorites')) {
            return;
        }

        Schema::create('devotional_favorites', function (Blueprint $table): void {
            $table->id();
            // users.id is unsigned INT (Symfony schema preserved via increments('id')),
            // so user_id has to match width — foreignId would create bigint and fail FK.
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('devotional_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'devotional_id'], 'devotional_favorites_user_devotional_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devotional_favorites');
    }
};
