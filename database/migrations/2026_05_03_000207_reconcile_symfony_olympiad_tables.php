<?php

declare(strict_types=1);

use App\Domain\Migration\Support\ReconcileTableHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('question') && ! Schema::hasTable('question_option')) {
            return;
        }

        ReconcileTableHelper::rename('question', 'olympiad_questions');
        ReconcileTableHelper::rename('question_option', 'olympiad_answers');
        ReconcileTableHelper::renameColumnIfPresent('olympiad_answers', 'correct', 'is_correct');
    }

    public function down(): void
    {
        ReconcileTableHelper::renameColumnIfPresent('olympiad_answers', 'is_correct', 'correct');
        ReconcileTableHelper::rename('olympiad_answers', 'question_option');
        ReconcileTableHelper::rename('olympiad_questions', 'question');
    }
};
