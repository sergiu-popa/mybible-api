<?php

declare(strict_types=1);

namespace App\Domain\Reference\Formatter\Languages;

use App\Domain\Reference\Exceptions\InvalidReferenceException;

final class EnglishFormatter implements LanguageFormatter
{
    /**
     * @var array<string, string>
     */
    private const NAME_TO_ABBREV = [
        'Genesis' => 'GEN',
        'Gen' => 'GEN',
        'Exodus' => 'EXO',
        'Exod' => 'EXO',
        'Exo' => 'EXO',
        'Leviticus' => 'LEV',
        'Lev' => 'LEV',
        'Numbers' => 'NUM',
        'Num' => 'NUM',
        'Deuteronomy' => 'DEU',
        'Deut' => 'DEU',
        'Joshua' => 'JOS',
        'Josh' => 'JOS',
        'Judges' => 'JDG',
        'Judg' => 'JDG',
        'Ruth' => 'RUT',
        '1 Samuel' => '1SA',
        '1 Sam' => '1SA',
        '2 Samuel' => '2SA',
        '2 Sam' => '2SA',
        '1 Kings' => '1KI',
        '2 Kings' => '2KI',
        '1 Chronicles' => '1CH',
        '1 Chr' => '1CH',
        '2 Chronicles' => '2CH',
        '2 Chr' => '2CH',
        'Ezra' => 'EZR',
        'Nehemiah' => 'NEH',
        'Neh' => 'NEH',
        'Esther' => 'EST',
        'Esth' => 'EST',
        'Job' => 'JOB',
        'Psalms' => 'PSA',
        'Psalm' => 'PSA',
        'Ps' => 'PSA',
        'Proverbs' => 'PRO',
        'Prov' => 'PRO',
        'Ecclesiastes' => 'ECC',
        'Eccl' => 'ECC',
        'Song of Songs' => 'SNG',
        'Song of Solomon' => 'SNG',
        'Isaiah' => 'ISA',
        'Isa' => 'ISA',
        'Jeremiah' => 'JER',
        'Jer' => 'JER',
        'Lamentations' => 'LAM',
        'Lam' => 'LAM',
        'Ezekiel' => 'EZK',
        'Ezek' => 'EZK',
        'Daniel' => 'DAN',
        'Dan' => 'DAN',
        'Hosea' => 'HOS',
        'Hos' => 'HOS',
        'Joel' => 'JOL',
        'Amos' => 'AMO',
        'Obadiah' => 'OBA',
        'Obad' => 'OBA',
        'Jonah' => 'JON',
        'Micah' => 'MIC',
        'Mic' => 'MIC',
        'Nahum' => 'NAM',
        'Nah' => 'NAM',
        'Habakkuk' => 'HAB',
        'Hab' => 'HAB',
        'Zephaniah' => 'ZEP',
        'Zeph' => 'ZEP',
        'Haggai' => 'HAG',
        'Hag' => 'HAG',
        'Zechariah' => 'ZEC',
        'Zech' => 'ZEC',
        'Malachi' => 'MAL',
        'Mal' => 'MAL',
        'Matthew' => 'MAT',
        'Matt' => 'MAT',
        'Mark' => 'MRK',
        'Luke' => 'LUK',
        'John' => 'JHN',
        'Acts' => 'ACT',
        'Romans' => 'ROM',
        'Rom' => 'ROM',
        '1 Corinthians' => '1CO',
        '1 Cor' => '1CO',
        '2 Corinthians' => '2CO',
        '2 Cor' => '2CO',
        'Galatians' => 'GAL',
        'Gal' => 'GAL',
        'Ephesians' => 'EPH',
        'Eph' => 'EPH',
        'Philippians' => 'PHP',
        'Phil' => 'PHP',
        'Colossians' => 'COL',
        'Col' => 'COL',
        '1 Thessalonians' => '1TH',
        '1 Thess' => '1TH',
        '2 Thessalonians' => '2TH',
        '2 Thess' => '2TH',
        '1 Timothy' => '1TI',
        '1 Tim' => '1TI',
        '2 Timothy' => '2TI',
        '2 Tim' => '2TI',
        'Titus' => 'TIT',
        'Philemon' => 'PHM',
        'Phlm' => 'PHM',
        'Hebrews' => 'HEB',
        'Heb' => 'HEB',
        'James' => 'JAS',
        'Jas' => 'JAS',
        '1 Peter' => '1PE',
        '1 Pet' => '1PE',
        '2 Peter' => '2PE',
        '2 Pet' => '2PE',
        '1 John' => '1JN',
        '2 John' => '2JN',
        '3 John' => '3JN',
        'Jude' => 'JUD',
        'Revelation' => 'REV',
        'Rev' => 'REV',
    ];

    /**
     * @var array<string, string>
     */
    private const ABBREV_TO_NAME = [
        'GEN' => 'Genesis',
        'EXO' => 'Exodus',
        'LEV' => 'Leviticus',
        'NUM' => 'Numbers',
        'DEU' => 'Deuteronomy',
        'JOS' => 'Joshua',
        'JDG' => 'Judges',
        'RUT' => 'Ruth',
        '1SA' => '1 Samuel',
        '2SA' => '2 Samuel',
        '1KI' => '1 Kings',
        '2KI' => '2 Kings',
        '1CH' => '1 Chronicles',
        '2CH' => '2 Chronicles',
        'EZR' => 'Ezra',
        'NEH' => 'Nehemiah',
        'EST' => 'Esther',
        'JOB' => 'Job',
        'PSA' => 'Psalms',
        'PRO' => 'Proverbs',
        'ECC' => 'Ecclesiastes',
        'SNG' => 'Song of Songs',
        'ISA' => 'Isaiah',
        'JER' => 'Jeremiah',
        'LAM' => 'Lamentations',
        'EZK' => 'Ezekiel',
        'DAN' => 'Daniel',
        'HOS' => 'Hosea',
        'JOL' => 'Joel',
        'AMO' => 'Amos',
        'OBA' => 'Obadiah',
        'JON' => 'Jonah',
        'MIC' => 'Micah',
        'NAM' => 'Nahum',
        'HAB' => 'Habakkuk',
        'ZEP' => 'Zephaniah',
        'HAG' => 'Haggai',
        'ZEC' => 'Zechariah',
        'MAL' => 'Malachi',
        'MAT' => 'Matthew',
        'MRK' => 'Mark',
        'LUK' => 'Luke',
        'JHN' => 'John',
        'ACT' => 'Acts',
        'ROM' => 'Romans',
        '1CO' => '1 Corinthians',
        '2CO' => '2 Corinthians',
        'GAL' => 'Galatians',
        'EPH' => 'Ephesians',
        'PHP' => 'Philippians',
        'COL' => 'Colossians',
        '1TH' => '1 Thessalonians',
        '2TH' => '2 Thessalonians',
        '1TI' => '1 Timothy',
        '2TI' => '2 Timothy',
        'TIT' => 'Titus',
        'PHM' => 'Philemon',
        'HEB' => 'Hebrews',
        'JAS' => 'James',
        '1PE' => '1 Peter',
        '2PE' => '2 Peter',
        '1JN' => '1 John',
        '2JN' => '2 John',
        '3JN' => '3 John',
        'JUD' => 'Jude',
        'REV' => 'Revelation',
    ];

    public function bookName(string $abbreviation): string
    {
        if (! isset(self::ABBREV_TO_NAME[$abbreviation])) {
            throw InvalidReferenceException::unknownBook($abbreviation, $abbreviation);
        }

        return self::ABBREV_TO_NAME[$abbreviation];
    }

    public function abbreviation(string $localized): string
    {
        if (! isset(self::NAME_TO_ABBREV[$localized])) {
            throw InvalidReferenceException::unknownBook($localized, $localized);
        }

        return self::NAME_TO_ABBREV[$localized];
    }

    public function linkifyRegex(): string
    {
        // Multi-word names first; longer aliases before shorter to win the alternation.
        return '/(1 Chronicles|1 Corinthians|1 Kings|1 Peter|1 Samuel|1 Thessalonians|1 Timothy|1 Chr|1 Cor|1 Pet|1 Sam|1 Thess|1 Tim|1 John|2 Chronicles|2 Corinthians|2 Kings|2 Peter|2 Samuel|2 Thessalonians|2 Timothy|2 Chr|2 Cor|2 Pet|2 Sam|2 Thess|2 Tim|2 John|3 John|Song of Solomon|Song of Songs|Acts|Amos|Colossians|Daniel|Deuteronomy|Ecclesiastes|Ephesians|Esther|Exodus|Ezekiel|Ezra|Galatians|Genesis|Habakkuk|Haggai|Hebrews|Hosea|Isaiah|James|Jeremiah|Job|Joel|John|Jonah|Joshua|Jude|Judges|Lamentations|Leviticus|Luke|Malachi|Mark|Matthew|Micah|Nahum|Nehemiah|Numbers|Obadiah|Philemon|Philippians|Proverbs|Psalms|Psalm|Revelation|Romans|Ruth|Titus|Zechariah|Zephaniah|Exod|Exo|Dan|Deut|Eccl|Eph|Esth|Gal|Gen|Hab|Hag|Heb|Hos|Isa|Jas|Jer|Josh|Judg|Lam|Lev|Mal|Matt|Mic|Nah|Neh|Num|Obad|Phil|Phlm|Prov|Ps|Rev|Rom|Zech|Zeph) {1}([\d,:;-]+ *[\d,:-]+)/mi';
    }

    public function defaultVersion(): string
    {
        return 'KJV';
    }
}
