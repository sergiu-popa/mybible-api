<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('news')) {
            return;
        }

        Schema::create('news', function (Blueprint $table): void {
            $table->id();
            $table->char('language', 2);
            $table->string('title');
            $table->text('summary');
            $table->longText('content')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['language', 'published_at'], 'news_language_published_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
