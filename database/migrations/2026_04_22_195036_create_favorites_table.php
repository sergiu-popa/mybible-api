<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table): void {
            $table->id();
            // users.id is unsigned INT (Symfony schema preserved via increments('id')),
            // so user_id has to match width — foreignId would create bigint and fail FK.
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            // ON DELETE SET NULL realizes the virtual "Uncategorized" fallback (AC 4):
            // deleting a category leaves its favorites intact with category_id = NULL.
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('favorite_categories')
                ->nullOnDelete();
            $table->string('reference', 255);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'category_id']);
            $table->index(['user_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
