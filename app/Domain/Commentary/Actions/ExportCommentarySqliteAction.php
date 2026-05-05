<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Actions;

use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use App\Domain\Commentary\Support\CommentarySqliteRevisionResolver;
use App\Domain\Commentary\Support\CommentarySqliteSchemaBuilder;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Builds the per-source per-language SQLite bundle for a commentary
 * (source) and its `source_commentary_id`-linked translations.
 *
 * Result shape:
 * - one row per `(book, chapter, position)` triplet from the source
 * - each translation's matching row populates `content_<target_lang>`
 * - missing translations leave the column NULL — mobile clients use
 *   `COALESCE(content_user_lang, content_en)` to fall back
 *
 * The action runs against a tmp file via raw `sqlite` PDO (not a Laravel
 * DB connection) so the export is not coupled to `config/database.php`.
 * Output is uploaded to S3 at `commentaries/{slug}/v{n}.sqlite`.
 *
 * @phpstan-type ExportResult array{url: ?string, path: string, revision: int, languages: list<string>, exported_at: string}
 */
final class ExportCommentarySqliteAction
{
    public function __construct(
        private readonly CommentarySqliteSchemaBuilder $schema,
        private readonly CommentarySqliteRevisionResolver $revisionResolver,
    ) {}

    /**
     * @return ExportResult
     */
    public function execute(Commentary $source): array
    {
        // The export schema reserves a `content_<lang>` column per
        // allow-listed language; if the source's language sits outside
        // that allow-list, it has no column to write into and would be
        // silently dropped from the artefact (only `meta.source_language`
        // would hint at it). Fail fast so the import job records a clear
        // error rather than producing a misleading file.
        $sourceLanguage = (string) $source->language;
        if (! in_array($sourceLanguage, CommentarySqliteSchemaBuilder::ALLOWED_LANGUAGES, true)) {
            throw new RuntimeException(sprintf(
                'Cannot export commentary #%d: source language "%s" is not in the SQLite export allow-list (%s).',
                (int) $source->id,
                $sourceLanguage,
                implode(', ', CommentarySqliteSchemaBuilder::ALLOWED_LANGUAGES),
            ));
        }

        $tmpPath = storage_path('app/tmp/' . Str::uuid()->toString() . '.sqlite');
        $tmpDir = dirname($tmpPath);
        if (! is_dir($tmpDir) && ! mkdir($tmpDir, 0775, true) && ! is_dir($tmpDir)) {
            throw new RuntimeException(sprintf('Could not create tmp dir %s', $tmpDir));
        }

        $revision = $this->revisionResolver->next((string) $source->slug);

        try {
            [$pdo, $populatedLanguages] = $this->writeFile($tmpPath, $source, $revision);
            unset($pdo);

            $key = sprintf('commentaries/%s/v%d.sqlite', $source->slug, $revision);
            $disk = Storage::disk((string) config('filesystems.default'));
            // Stream the artefact rather than buffering it in memory: the
            // SDA × 7-language bundle reaches 30–80 MB and back-to-back
            // exports would push the worker past `memory_limit`.
            $stream = fopen($tmpPath, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Could not open generated SQLite file.');
            }

            try {
                $disk->put($key, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
            // Surface a usable URL when the disk supports it; otherwise
            // store the bare key so admins can derive the URL by
            // convention (e.g. signed URLs in production).
            $url = $this->safeUrl($disk, $key);

            return [
                'url' => $url,
                'path' => $key,
                'revision' => $revision,
                'languages' => $populatedLanguages,
                'exported_at' => Carbon::now()->toIso8601String(),
            ];
        } finally {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    /**
     * @return array{0: PDO, 1: list<string>}
     */
    private function writeFile(string $path, Commentary $source, int $revision): array
    {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->schema->build($pdo);

        $sourceLanguage = (string) $source->language;
        $translations = Commentary::query()
            ->where('source_commentary_id', $source->id)
            ->get()
            ->keyBy(fn (Commentary $c): string => (string) $c->language);

        $populatedLanguages = [$sourceLanguage];
        foreach ($translations as $translation) {
            $populatedLanguages[] = (string) $translation->language;
        }
        $populatedLanguages = array_values(array_unique($populatedLanguages));

        $this->writeMeta($pdo, $source, $revision, $populatedLanguages);

        $sourceTexts = CommentaryText::query()
            ->where('commentary_id', $source->id)
            ->orderBy('book')
            ->orderBy('chapter')
            ->orderBy('position')
            ->get();

        $translationTextsByLanguage = [];
        foreach ($translations as $language => $translation) {
            $translationTextsByLanguage[$language] = CommentaryText::query()
                ->where('commentary_id', $translation->id)
                ->get()
                ->keyBy(fn (CommentaryText $row): string => sprintf(
                    '%s|%d|%d',
                    $row->book,
                    $row->chapter,
                    $row->position,
                ));
        }

        $columns = ['book', 'chapter', 'position', 'verse_label', 'verse_from', 'verse_to'];
        foreach (CommentarySqliteSchemaBuilder::ALLOWED_LANGUAGES as $lang) {
            $columns[] = 'content_' . $lang;
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $insertSql = sprintf(
            'INSERT INTO commentary_text (%s) VALUES (%s)',
            implode(', ', $columns),
            $placeholders,
        );

        $statement = $pdo->prepare($insertSql);

        $pdo->beginTransaction();

        foreach ($sourceTexts as $row) {
            $key = sprintf('%s|%d|%d', $row->book, $row->chapter, $row->position);
            $values = [
                $row->book,
                $row->chapter,
                $row->position,
                $row->verse_label,
                $row->verse_from,
                $row->verse_to,
            ];

            foreach (CommentarySqliteSchemaBuilder::ALLOWED_LANGUAGES as $lang) {
                if ($lang === $sourceLanguage) {
                    $values[] = $this->preferredContent($row);

                    continue;
                }

                $translationRows = $translationTextsByLanguage[$lang] ?? null;
                if ($translationRows === null) {
                    $values[] = null;

                    continue;
                }

                /** @var CommentaryText|null $translationRow */
                $translationRow = $translationRows->get($key);
                $values[] = $translationRow !== null
                    ? $this->preferredContent($translationRow)
                    : null;
            }

            $statement->execute($values);
        }

        $pdo->commit();

        try {
            $pdo->exec('PRAGMA optimize;');
            $pdo->exec('VACUUM;');
        } catch (Throwable) {
            // VACUUM is best-effort — failures should not break the export.
        }

        return [$pdo, $populatedLanguages];
    }

    private function preferredContent(CommentaryText $row): ?string
    {
        $candidates = [$row->with_references, $row->plain, $row->original, $row->content];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $populatedLanguages
     */
    private function writeMeta(PDO $pdo, Commentary $source, int $revision, array $populatedLanguages): void
    {
        $statement = $pdo->prepare('INSERT INTO meta (key, value) VALUES (?, ?)');

        /** @var array<string, string>|string|null $rawName */
        $rawName = $source->name;
        $name = is_array($rawName) ? json_encode($rawName) : (string) $rawName;

        $entries = [
            'schema_version' => (string) CommentarySqliteSchemaBuilder::SCHEMA_VERSION,
            'source_slug' => (string) $source->slug,
            'source_name' => $name === false ? '' : (string) $name,
            'source_language' => (string) $source->language,
            'languages' => implode(',', $populatedLanguages),
            'exported_at' => Carbon::now()->toIso8601String(),
            'exported_revision' => 'v' . $revision,
        ];

        foreach ($entries as $key => $value) {
            $statement->execute([$key, $value]);
        }
    }

    private function safeUrl(Filesystem $disk, string $key): ?string
    {
        try {
            /** @var mixed $url */
            $url = $disk->url($key);

            return is_string($url) ? $url : null;
        } catch (Throwable) {
            return null;
        }
    }
}
