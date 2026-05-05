<?php

declare(strict_types=1);

namespace App\Domain\Commentary\QueryBuilders;

use App\Domain\Commentary\Enums\CommentaryErrorReportStatus;
use App\Domain\Commentary\Models\CommentaryErrorReport;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<CommentaryErrorReport>
 */
final class CommentaryErrorReportQueryBuilder extends Builder
{
    public function pending(): self
    {
        return $this->where('status', CommentaryErrorReportStatus::Pending->value);
    }

    public function forStatus(CommentaryErrorReportStatus $status): self
    {
        return $this->where('status', $status->value);
    }

    public function forCommentaryText(int $commentaryTextId): self
    {
        return $this->where('commentary_text_id', $commentaryTextId);
    }
}
