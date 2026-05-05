<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Commentary\Actions;

use App\Domain\Commentary\Actions\ExportCommentarySqliteAction;
use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use App\Domain\Commentary\Support\CommentarySqliteRevisionResolver;
use App\Domain\Commentary\Support\CommentarySqliteSchemaBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PDO;
use PDOStatement;
use Tests\TestCase;

final class ExportCommentarySqliteActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_writes_meta_and_per_language_columns(): void
    {
        Storage::fake('local');
        config()->set('filesystems.default', 'local');

        $source = Commentary::factory()->create([
            'language' => 'ro',
            'slug' => 'sda',
        ]);
        $en = Commentary::factory()->create([
            'language' => 'en',
            'source_commentary_id' => $source->id,
        ]);

        // 3 source rows
        foreach ([1, 2, 3] as $position) {
            CommentaryText::factory()->create([
                'commentary_id' => $source->id,
                'book' => 'GEN',
                'chapter' => 1,
                'position' => $position,
                'with_references' => "<p>RO {$position}</p>",
                'plain' => "<p>RO {$position}</p>",
            ]);
        }

        // 2 of those have an English translation, the 3rd does not
        foreach ([1, 2] as $position) {
            CommentaryText::factory()->create([
                'commentary_id' => $en->id,
                'book' => 'GEN',
                'chapter' => 1,
                'position' => $position,
                'with_references' => "<p>EN {$position}</p>",
                'plain' => "<p>EN {$position}</p>",
            ]);
        }

        $action = new ExportCommentarySqliteAction(
            new CommentarySqliteSchemaBuilder,
            new CommentarySqliteRevisionResolver,
        );

        $result = $action->execute($source);

        self::assertSame(1, $result['revision']);
        self::assertSame('commentaries/sda/v1.sqlite', $result['path']);
        self::assertContains('ro', $result['languages']);
        self::assertContains('en', $result['languages']);

        Storage::disk('local')->assertExists($result['path']);

        $bytes = Storage::disk('local')->get($result['path']);
        $tmp = tempnam(sys_get_temp_dir(), 'sqlite-test-');
        file_put_contents($tmp, $bytes);

        try {
            $pdo = new PDO('sqlite:' . $tmp);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // meta table populated
            $rows = $this->fetchAll($pdo, 'SELECT key, value FROM meta', PDO::FETCH_KEY_PAIR);
            self::assertSame('sda', $rows['source_slug']);
            self::assertSame('ro', $rows['source_language']);
            self::assertSame('v1', $rows['exported_revision']);

            // commentary_text content columns
            $contents = $this->fetchAll(
                $pdo,
                'SELECT position, content_ro, content_en, content_hu FROM commentary_text ORDER BY position',
                PDO::FETCH_ASSOC,
            );

            self::assertCount(3, $contents);
            self::assertSame('<p>RO 1</p>', $contents[0]['content_ro']);
            self::assertSame('<p>EN 1</p>', $contents[0]['content_en']);
            self::assertNull($contents[0]['content_hu']);

            self::assertSame('<p>RO 3</p>', $contents[2]['content_ro']);
            self::assertNull($contents[2]['content_en']);

            // pragmas + indexes
            $pragma = $this->fetchColumn($pdo, 'PRAGMA application_id;');
            self::assertSame(CommentarySqliteSchemaBuilder::APPLICATION_ID, (int) $pragma);

            $userVersion = $this->fetchColumn($pdo, 'PRAGMA user_version;');
            self::assertSame(CommentarySqliteSchemaBuilder::SCHEMA_VERSION, (int) $userVersion);

            $indexes = $this->fetchAll($pdo, "SELECT name FROM sqlite_master WHERE type = 'index'", PDO::FETCH_COLUMN);
            self::assertContains('commentary_text_book_chapter_idx', $indexes);
            self::assertContains('commentary_text_verse_lookup_idx', $indexes);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * @return array<int|string, mixed>
     */
    private function fetchAll(PDO $pdo, string $sql, int $mode): array
    {
        $statement = $pdo->query($sql);
        self::assertInstanceOf(PDOStatement::class, $statement);

        return $statement->fetchAll($mode);
    }

    private function fetchColumn(PDO $pdo, string $sql): mixed
    {
        $statement = $pdo->query($sql);
        self::assertInstanceOf(PDOStatement::class, $statement);

        return $statement->fetchColumn();
    }
}
