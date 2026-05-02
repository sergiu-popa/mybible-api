<?php

declare(strict_types=1);

use App\Domain\Migration\Actions\BackfillLegacyBookAbbreviationsAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Temporary mapping table feeding the book-abbreviation backfill
 * (MBA-023 §13/§14). Seeded with Romanian + English long-form names
 * → USFM-3. Consumed by:
 *   - {@see BackfillLegacyBookAbbreviationsAction}
 *   - MBA-031 ETL when rewriting `bible_verses` references
 *
 * Dropped in MBA-032 cleanup. Document the consumer here so a future
 * reader does not delete it as orphaned scaffolding.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('_legacy_book_abbreviation_map')) {
            return;
        }

        Schema::create('_legacy_book_abbreviation_map', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 64);
            $table->char('language', 2);
            $table->string('abbreviation', 8);

            $table->unique(['name', 'language']);
        });

        DB::table('_legacy_book_abbreviation_map')->insert($this->seed());
    }

    public function down(): void
    {
        Schema::dropIfExists('_legacy_book_abbreviation_map');
    }

    /**
     * @return list<array{name: string, language: string, abbreviation: string}>
     */
    private function seed(): array
    {
        $rows = [];

        foreach ($this->mappings() as $abbreviation => [$en, $ro]) {
            $rows[] = ['name' => $en, 'language' => 'en', 'abbreviation' => $abbreviation];
            $rows[] = ['name' => $ro, 'language' => 'ro', 'abbreviation' => $abbreviation];
        }

        return $rows;
    }

    /**
     * Pairs of [English, Romanian] long-form names keyed by USFM-3.
     * Single source of truth for legacy-name → USFM-3 conversion.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function mappings(): array
    {
        return [
            'GEN' => ['Genesis', 'Geneza'],
            'EXO' => ['Exodus', 'Exodul'],
            'LEV' => ['Leviticus', 'Leviticul'],
            'NUM' => ['Numbers', 'Numerii'],
            'DEU' => ['Deuteronomy', 'Deuteronomul'],
            'JOS' => ['Joshua', 'Iosua'],
            'JDG' => ['Judges', 'Judecători'],
            'RUT' => ['Ruth', 'Rut'],
            '1SA' => ['1 Samuel', '1 Samuel'],
            '2SA' => ['2 Samuel', '2 Samuel'],
            '1KI' => ['1 Kings', '1 Împărați'],
            '2KI' => ['2 Kings', '2 Împărați'],
            '1CH' => ['1 Chronicles', '1 Cronici'],
            '2CH' => ['2 Chronicles', '2 Cronici'],
            'EZR' => ['Ezra', 'Ezra'],
            'NEH' => ['Nehemiah', 'Neemia'],
            'EST' => ['Esther', 'Estera'],
            'JOB' => ['Job', 'Iov'],
            'PSA' => ['Psalms', 'Psalmii'],
            'PRO' => ['Proverbs', 'Proverbele'],
            'ECC' => ['Ecclesiastes', 'Eclesiastul'],
            'SNG' => ['Song of Solomon', 'Cântarea cântărilor'],
            'ISA' => ['Isaiah', 'Isaia'],
            'JER' => ['Jeremiah', 'Ieremia'],
            'LAM' => ['Lamentations', 'Plângerile lui Ieremia'],
            'EZK' => ['Ezekiel', 'Ezechiel'],
            'DAN' => ['Daniel', 'Daniel'],
            'HOS' => ['Hosea', 'Osea'],
            'JOL' => ['Joel', 'Ioel'],
            'AMO' => ['Amos', 'Amos'],
            'OBA' => ['Obadiah', 'Obadia'],
            'JON' => ['Jonah', 'Iona'],
            'MIC' => ['Micah', 'Mica'],
            'NAM' => ['Nahum', 'Naum'],
            'HAB' => ['Habakkuk', 'Habacuc'],
            'ZEP' => ['Zephaniah', 'Țefania'],
            'HAG' => ['Haggai', 'Hagai'],
            'ZEC' => ['Zechariah', 'Zaharia'],
            'MAL' => ['Malachi', 'Maleahi'],
            'MAT' => ['Matthew', 'Matei'],
            'MRK' => ['Mark', 'Marcu'],
            'LUK' => ['Luke', 'Luca'],
            'JHN' => ['John', 'Ioan'],
            'ACT' => ['Acts', 'Faptele apostolilor'],
            'ROM' => ['Romans', 'Romani'],
            '1CO' => ['1 Corinthians', '1 Corinteni'],
            '2CO' => ['2 Corinthians', '2 Corinteni'],
            'GAL' => ['Galatians', 'Galateni'],
            'EPH' => ['Ephesians', 'Efeseni'],
            'PHP' => ['Philippians', 'Filipeni'],
            'COL' => ['Colossians', 'Coloseni'],
            '1TH' => ['1 Thessalonians', '1 Tesaloniceni'],
            '2TH' => ['2 Thessalonians', '2 Tesaloniceni'],
            '1TI' => ['1 Timothy', '1 Timotei'],
            '2TI' => ['2 Timothy', '2 Timotei'],
            'TIT' => ['Titus', 'Tit'],
            'PHM' => ['Philemon', 'Filimon'],
            'HEB' => ['Hebrews', 'Evrei'],
            'JAS' => ['James', 'Iacov'],
            '1PE' => ['1 Peter', '1 Petru'],
            '2PE' => ['2 Peter', '2 Petru'],
            '1JN' => ['1 John', '1 Ioan'],
            '2JN' => ['2 John', '2 Ioan'],
            '3JN' => ['3 John', '3 Ioan'],
            'JUD' => ['Jude', 'Iuda'],
            'REV' => ['Revelation', 'Apocalipsa'],
        ];
    }
};
