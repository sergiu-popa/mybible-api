<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_plan_day_fragments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reading_plan_day_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('type');
            $table->json('content');
            $table->timestamps();

            $table->unique(['reading_plan_day_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_plan_day_fragments');
    }
};
