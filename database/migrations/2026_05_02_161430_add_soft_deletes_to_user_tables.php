<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('favorites', function (Blueprint $table): void {
            $table->softDeletes();
            $table->index(['user_id', 'deleted_at'], 'favorites_user_deleted_idx');
            $table->index(['user_id', 'updated_at'], 'favorites_user_updated_idx');
        });

        Schema::table('favorite_categories', function (Blueprint $table): void {
            $table->softDeletes();
            $table->index(['user_id', 'deleted_at'], 'favorite_categories_user_deleted_idx');
            $table->index(['user_id', 'updated_at'], 'favorite_categories_user_updated_idx');
        });

        Schema::table('notes', function (Blueprint $table): void {
            $table->softDeletes();
            $table->index(['user_id', 'deleted_at'], 'notes_user_deleted_idx');
            $table->index(['user_id', 'updated_at'], 'notes_user_updated_idx');
        });

        // devotional_favorites has no updated_at — add both columns
        Schema::table('devotional_favorites', function (Blueprint $table): void {
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
            $table->index(['user_id', 'deleted_at'], 'devotional_favorites_user_deleted_idx');
            $table->index(['user_id', 'updated_at'], 'devotional_favorites_user_updated_idx');
        });

        // hymnal_favorites has no updated_at — add both columns
        Schema::table('hymnal_favorites', function (Blueprint $table): void {
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
            $table->index(['user_id', 'deleted_at'], 'hymnal_favorites_user_deleted_idx');
            $table->index(['user_id', 'updated_at'], 'hymnal_favorites_user_updated_idx');
        });

        Schema::table('sabbath_school_answers', function (Blueprint $table): void {
            $table->softDeletes();
            $table->index(['user_id', 'deleted_at'], 'ss_answers_user_deleted_idx');
            $table->index(['user_id', 'updated_at'], 'ss_answers_user_updated_idx');
        });

        Schema::table('sabbath_school_highlights', function (Blueprint $table): void {
            $table->softDeletes();
            $table->index(['user_id', 'deleted_at'], 'ss_highlights_user_deleted_idx');
            $table->index(['user_id', 'updated_at'], 'ss_highlights_user_updated_idx');
        });

        Schema::table('sabbath_school_favorites', function (Blueprint $table): void {
            $table->softDeletes();
            $table->index(['user_id', 'deleted_at'], 'ss_favorites_user_deleted_idx');
            $table->index(['user_id', 'updated_at'], 'ss_favorites_user_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('favorites', function (Blueprint $table): void {
            $table->dropIndex('favorites_user_deleted_idx');
            $table->dropIndex('favorites_user_updated_idx');
            $table->dropSoftDeletes();
        });

        Schema::table('favorite_categories', function (Blueprint $table): void {
            $table->dropIndex('favorite_categories_user_deleted_idx');
            $table->dropIndex('favorite_categories_user_updated_idx');
            $table->dropSoftDeletes();
        });

        Schema::table('notes', function (Blueprint $table): void {
            $table->dropIndex('notes_user_deleted_idx');
            $table->dropIndex('notes_user_updated_idx');
            $table->dropSoftDeletes();
        });

        Schema::table('devotional_favorites', function (Blueprint $table): void {
            $table->dropIndex('devotional_favorites_user_deleted_idx');
            $table->dropIndex('devotional_favorites_user_updated_idx');
            $table->dropSoftDeletes();
            $table->dropColumn('updated_at');
        });

        Schema::table('hymnal_favorites', function (Blueprint $table): void {
            $table->dropIndex('hymnal_favorites_user_deleted_idx');
            $table->dropIndex('hymnal_favorites_user_updated_idx');
            $table->dropSoftDeletes();
            $table->dropColumn('updated_at');
        });

        Schema::table('sabbath_school_answers', function (Blueprint $table): void {
            $table->dropIndex('ss_answers_user_deleted_idx');
            $table->dropIndex('ss_answers_user_updated_idx');
            $table->dropSoftDeletes();
        });

        Schema::table('sabbath_school_highlights', function (Blueprint $table): void {
            $table->dropIndex('ss_highlights_user_deleted_idx');
            $table->dropIndex('ss_highlights_user_updated_idx');
            $table->dropSoftDeletes();
        });

        Schema::table('sabbath_school_favorites', function (Blueprint $table): void {
            $table->dropIndex('ss_favorites_user_deleted_idx');
            $table->dropIndex('ss_favorites_user_updated_idx');
            $table->dropSoftDeletes();
        });
    }
};
