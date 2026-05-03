<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Reconcile;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Assert;

final class OlympiadReconcileTest extends ReconcileTestCase
{
    use RefreshDatabase;

    public function test_it_renames_question_tables_and_correct_to_is_correct(): void
    {
        // Drop dependent tables first (FK to olympiad_questions/olympiad_answers).
        Schema::dropIfExists('olympiad_attempt_answers');
        Schema::dropIfExists('olympiad_attempts');
        Schema::dropIfExists('olympiad_answers');
        Schema::dropIfExists('olympiad_questions');

        $this->recreateLegacyTable('question', function (Blueprint $t): void {
            $t->id();
            $t->string('book', 8);
            $t->string('language', 3);
            $t->text('text');
            $t->timestamps();
        });

        $this->recreateLegacyTable('question_option', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('question_id');
            $t->text('text');
            $t->boolean('correct')->default(false);
        });

        $migration = $this->loadMigration('2026_05_03_000207_reconcile_symfony_olympiad_tables.php');
        Assert::assertTrue(method_exists($migration, 'up'));
        $migration->up();

        $this->assertTableExists('olympiad_questions');
        $this->assertTableExists('olympiad_answers');
        $this->assertTableMissing('question');
        $this->assertTableMissing('question_option');

        $this->assertColumnExists('olympiad_answers', 'is_correct');
        $this->assertFalse(Schema::hasColumn('olympiad_answers', 'correct'));
    }
}
