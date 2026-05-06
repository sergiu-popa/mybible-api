<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 2 — collapse the legacy `hymnal_verses` rows into
 * `hymnal_songs.stanzas` JSON. JSON shape locked by the existing
 * frontend contract: `{language: [{index, text, is_chorus}]}`. Songs
 * whose `stanzas` is already populated are skipped (idempotent).
 *
 * Symfony stored the chorus as a verse with `number = 'C'`; we flip
 * `is_chorus=true` on that row and emit numeric stanza indexes for
 * the rest in document order.
 */
final class EtlHymnalStanzasJob extends BaseEtlJob
{
    public static function slug(): string
    {
        return 'etl_hymnal_stanzas';
    }

    protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult
    {
        if (! Schema::hasTable('hymnal_verses') || ! Schema::hasTable('hymnal_songs')) {
            return new EtlSubJobResult;
        }

        $songs = DB::table('hymnal_songs')
            ->join('hymnal_books', 'hymnal_books.id', '=', 'hymnal_songs.hymnal_book_id')
            ->select(
                'hymnal_songs.id',
                'hymnal_songs.stanzas',
                'hymnal_books.language',
            )
            ->get();

        $total = $songs->count();
        $processed = 0;
        $succeeded = 0;

        foreach ($songs as $song) {
            $processed++;
            $existing = $this->decode($song->stanzas);

            if ($existing !== [] && $existing !== null) {
                $reporter->progress($importJob, $processed, $total);

                continue;
            }

            $verses = DB::table('hymnal_verses')
                ->where('hymnal_song_id', $song->id)
                ->orderBy('id')
                ->get(['number', 'text']);

            if ($verses->isEmpty()) {
                $reporter->progress($importJob, $processed, $total);

                continue;
            }

            $stanzas = [];
            $index = 0;

            foreach ($verses as $verse) {
                $isChorus = strtoupper(trim((string) $verse->number)) === 'C';
                $stanzas[] = [
                    'index' => $isChorus ? null : ++$index,
                    'text' => (string) $verse->text,
                    'is_chorus' => $isChorus,
                ];
            }

            DB::table('hymnal_songs')
                ->where('id', $song->id)
                ->update([
                    'stanzas' => json_encode([
                        (string) $song->language => $stanzas,
                    ], JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);

            $succeeded++;
            $reporter->progress($importJob, $processed, $total);
        }

        return new EtlSubJobResult(
            processed: $processed,
            succeeded: $succeeded,
            skipped: $processed - $succeeded,
        );
    }

    private function decode(mixed $raw): mixed
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return json_decode($raw, true);
    }
}
