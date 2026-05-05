<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Commentary\Enums\CommentaryErrorReportStatus;
use App\Domain\Commentary\Models\CommentaryErrorReport;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommentaryErrorReport>
 */
final class CommentaryErrorReportFactory extends Factory
{
    protected $model = CommentaryErrorReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commentary_text_id' => CommentaryText::factory(),
            'user_id' => null,
            'device_id' => fake()->uuid(),
            // book/chapter/verse are denormalised from the parent
            // CommentaryText for triage filters (story §AC4). They are
            // overlaid in `configure()` once the FK resolves so the
            // factory's rows always agree with their parent — keeping
            // the denorm contract honest in tests.
            'book' => 'GEN',
            'chapter' => 1,
            'verse' => 1,
            'description' => fake()->sentence(),
            'status' => CommentaryErrorReportStatus::Pending,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ];
    }

    public function configure(): self
    {
        return $this->afterMaking(function (CommentaryErrorReport $report): void {
            $text = CommentaryText::query()->find($report->commentary_text_id);
            if (! $text instanceof CommentaryText) {
                return;
            }

            $report->forceFill([
                'book' => $text->book,
                'chapter' => $text->chapter,
                'verse' => $text->verse_from,
            ]);
        });
    }

    public function reviewed(): self
    {
        return $this->state(fn (): array => [
            'status' => CommentaryErrorReportStatus::Reviewed,
        ]);
    }

    public function fixed(): self
    {
        return $this->state(fn (): array => [
            'status' => CommentaryErrorReportStatus::Fixed,
            'reviewed_at' => now(),
        ]);
    }

    public function dismissed(): self
    {
        return $this->state(fn (): array => [
            'status' => CommentaryErrorReportStatus::Dismissed,
            'reviewed_at' => now(),
        ]);
    }
}
