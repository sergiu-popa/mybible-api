<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Commentary\Actions;

use App\Domain\Commentary\Actions\CreateCommentaryAction;
use App\Domain\Commentary\Actions\TranslateCommentaryAction;
use App\Domain\Commentary\DataTransferObjects\TranslateCommentaryData;
use App\Domain\Commentary\Exceptions\CommentaryNotCorrectedException;
use App\Domain\Commentary\Exceptions\TranslationTargetExistsException;
use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TranslateCommentaryActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepare_creates_target_commentary_when_absent(): void
    {
        $source = Commentary::factory()->create([
            'language' => 'ro',
            'slug' => 'sda-ro',
        ]);
        CommentaryText::factory()->create([
            'commentary_id' => $source->id,
            'plain' => '<p>x</p>',
        ]);

        $target = $this->makeAction()->prepare(new TranslateCommentaryData(
            sourceCommentaryId: (int) $source->id,
            targetLanguage: 'en',
        ));

        self::assertSame('en', $target->language);
        self::assertSame((int) $source->id, $target->source_commentary_id);
        self::assertStringContainsString('-en', (string) $target->slug);
    }

    public function test_prepare_refuses_when_source_has_uncorrected_rows(): void
    {
        $source = Commentary::factory()->create(['language' => 'ro']);
        CommentaryText::factory()->create([
            'commentary_id' => $source->id,
            'plain' => null,
        ]);

        $this->expectException(CommentaryNotCorrectedException::class);

        $this->makeAction()->prepare(new TranslateCommentaryData(
            sourceCommentaryId: (int) $source->id,
            targetLanguage: 'en',
        ));
    }

    public function test_prepare_throws_409_when_existing_target_and_no_overwrite(): void
    {
        $source = Commentary::factory()->create(['language' => 'ro']);
        CommentaryText::factory()->create([
            'commentary_id' => $source->id,
            'plain' => '<p>x</p>',
        ]);

        Commentary::factory()->create([
            'language' => 'en',
            'source_commentary_id' => $source->id,
        ]);

        $this->expectException(TranslationTargetExistsException::class);

        $this->makeAction()->prepare(new TranslateCommentaryData(
            sourceCommentaryId: (int) $source->id,
            targetLanguage: 'en',
        ));
    }

    public function test_prepare_overwrite_clears_existing_target_texts(): void
    {
        $source = Commentary::factory()->create(['language' => 'ro']);
        CommentaryText::factory()->create([
            'commentary_id' => $source->id,
            'plain' => '<p>x</p>',
        ]);

        $existingTarget = Commentary::factory()->create([
            'language' => 'en',
            'source_commentary_id' => $source->id,
        ]);
        foreach ([1, 2, 3] as $position) {
            CommentaryText::factory()->create([
                'commentary_id' => $existingTarget->id,
                'book' => 'GEN',
                'chapter' => 1,
                'position' => $position,
            ]);
        }

        $resolved = $this->makeAction()->prepare(new TranslateCommentaryData(
            sourceCommentaryId: (int) $source->id,
            targetLanguage: 'en',
            overwrite: true,
        ));

        self::assertSame((int) $existingTarget->id, (int) $resolved->id);
        self::assertSame(0, CommentaryText::query()->where('commentary_id', $existingTarget->id)->count());
    }

    private function makeAction(): TranslateCommentaryAction
    {
        return new TranslateCommentaryAction(new CreateCommentaryAction);
    }
}
