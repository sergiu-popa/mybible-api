<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\Reference\Exceptions\InvalidReferenceException;
use App\Domain\Reference\Parser\ReferenceParser;
use App\Domain\SabbathSchool\DataTransferObjects\ToggleSabbathSchoolHighlightData;
use App\Domain\SabbathSchool\DataTransferObjects\ToggleSabbathSchoolHighlightResult;
use App\Domain\SabbathSchool\Exceptions\InvalidSabbathSchoolPassageException;
use App\Domain\SabbathSchool\Models\SabbathSchoolHighlight;
use Illuminate\Support\Facades\DB;

final class ToggleSabbathSchoolHighlightAction
{
    public function __construct(
        private readonly ReferenceParser $parser = new ReferenceParser,
    ) {}

    /**
     * Flip the highlight state for a user on `(segment, passage)`.
     *
     * The canonical passage string is validated through the reference parser
     * at write time; any parse failure is re-raised as
     * {@see InvalidSabbathSchoolPassageException} so the HTTP handler can
     * render 422 without leaking reference-domain internals.
     *
     * @throws InvalidSabbathSchoolPassageException when the passage fails to parse
     */
    public function execute(ToggleSabbathSchoolHighlightData $data): ToggleSabbathSchoolHighlightResult
    {
        try {
            $this->parser->parseOne($data->passage);
        } catch (InvalidReferenceException $e) {
            throw InvalidSabbathSchoolPassageException::fromReferenceException($data->passage, $e);
        }

        return DB::transaction(function () use ($data): ToggleSabbathSchoolHighlightResult {
            $existing = SabbathSchoolHighlight::query()
                ->forUser($data->user)
                ->forSegment($data->segmentId)
                ->forPassage($data->passage)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->delete();

                return ToggleSabbathSchoolHighlightResult::deleted();
            }

            $highlight = SabbathSchoolHighlight::query()->create([
                'user_id' => $data->user->id,
                'sabbath_school_segment_id' => $data->segmentId,
                'passage' => $data->passage,
            ]);

            return ToggleSabbathSchoolHighlightResult::created($highlight);
        });
    }
}
