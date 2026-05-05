<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Support;

use PDO;

/**
 * Owns the per-source SQLite export DDL: `meta` table, `commentary_text`
 * table parameterised by populated languages, indexes, and pragmas.
 *
 * The language allow-list is intentionally hard-coded to match the
 * mobile contract; new languages require a coordinated schema bump.
 */
final class CommentarySqliteSchemaBuilder
{
    /** Languages that get a dedicated `content_<lang>` column on the export. */
    public const ALLOWED_LANGUAGES = ['ro', 'en', 'hu', 'es', 'fr', 'de', 'it'];

    /** Mobile clients sniff this with `PRAGMA application_id`. */
    public const APPLICATION_ID = 0x4D424342;

    /** Bumped together with any structural change to the export. */
    public const SCHEMA_VERSION = 1;

    public function build(PDO $pdo): void
    {
        $pdo->exec(sprintf('PRAGMA user_version = %d;', self::SCHEMA_VERSION));
        $pdo->exec(sprintf('PRAGMA application_id = %d;', self::APPLICATION_ID));

        $pdo->exec(<<<'SQL'
CREATE TABLE meta (
    key TEXT PRIMARY KEY,
    value TEXT
);
SQL);

        $contentColumns = array_map(
            static fn (string $lang): string => sprintf('content_%s TEXT', $lang),
            self::ALLOWED_LANGUAGES,
        );

        $pdo->exec(sprintf(
            <<<'SQL'
CREATE TABLE commentary_text (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    book TEXT NOT NULL,
    chapter INTEGER NOT NULL,
    position INTEGER NOT NULL,
    verse_label TEXT,
    verse_from INTEGER,
    verse_to INTEGER,
    %s,
    UNIQUE(book, chapter, position)
);
SQL,
            implode(",\n    ", $contentColumns),
        ));

        $pdo->exec('CREATE INDEX commentary_text_book_chapter_idx ON commentary_text (book, chapter);');
        $pdo->exec('CREATE INDEX commentary_text_verse_lookup_idx ON commentary_text (book, chapter, verse_from, verse_to);');
    }
}
