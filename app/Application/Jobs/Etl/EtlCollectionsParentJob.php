<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 2 — re-link `collection_topics.collection_id` from any legacy
 * join table that survived reconcile, and backfill
 * `collection_topics.image_cdn_url` from the Symfony source. Both
 * operations only touch rows where the target column is NULL, so
 * re-runs leave operator-curated rows alone.
 */
final class EtlCollectionsParentJob extends BaseEtlJob
{
    public static function slug(): string
    {
        return 'etl_collections_parent';
    }

    protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult
    {
        if (! Schema::hasTable('collection_topics')) {
            return new EtlSubJobResult;
        }

        $linked = $this->relinkParents();
        $reporter->progress($importJob, 1, 2);

        $imaged = $this->backfillCdnUrls();
        $reporter->progress($importJob, 2, 2);

        return new EtlSubJobResult(
            processed: $linked + $imaged,
            succeeded: $linked + $imaged,
        );
    }

    private function relinkParents(): int
    {
        if (! Schema::hasColumn('collection_topics', 'collection_id')) {
            return 0;
        }

        // Legacy join table name varies across Symfony exports; only act
        // when one is actually present.
        if (! Schema::hasTable('collection_topic_collection')) {
            return 0;
        }

        return (int) DB::affectingStatement(<<<'SQL'
            UPDATE collection_topics ct
            JOIN collection_topic_collection j ON j.collection_topic_id = ct.id
            SET ct.collection_id = j.collection_id
            WHERE ct.collection_id IS NULL
        SQL);
    }

    private function backfillCdnUrls(): int
    {
        if (! Schema::hasColumn('collection_topics', 'image_cdn_url')) {
            return 0;
        }

        if (! Schema::hasColumn('collection_topics', 'image_path')) {
            return 0;
        }

        $cdnBase = rtrim((string) config('filesystems.disks.s3.cdn_url', ''), '/');

        return (int) DB::affectingStatement(
            'UPDATE collection_topics
             SET image_cdn_url = CONCAT(?, "/", image_path)
             WHERE image_cdn_url IS NULL AND image_path IS NOT NULL AND image_path <> ""',
            [$cdnBase],
        );
    }
}
