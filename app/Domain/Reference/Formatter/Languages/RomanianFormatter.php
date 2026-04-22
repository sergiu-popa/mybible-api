<?php

declare(strict_types=1);

namespace App\Domain\Reference\Formatter\Languages;

use App\Domain\Reference\Exceptions\InvalidReferenceException;

final class RomanianFormatter implements LanguageFormatter
{
    /**
     * Localized name → canonical abbreviation.
     *
     * @var array<string, string>
     */
    private const NAME_TO_ABBREV = [
        'Geneza' => 'GEN',
        'Exodul' => 'EXO',
        'Leviticul' => 'LEV',
        'Numeri' => 'NUM',
        'Deuteronomul' => 'DEU',
        'Iosua' => 'JOS',
        'Judecători' => 'JDG',
        'Rut' => 'RUT',
        '1 Samuel' => '1SA',
        '2 Samuel' => '2SA',
        '1 Împăraţi' => '1KI',
        '2 Împăraţi' => '2KI',
        '1 Cronici' => '1CH',
        '2 Cronici' => '2CH',
        'Ezra' => 'EZR',
        'Neemia' => 'NEH',
        'Estera' => 'EST',
        'Iov' => 'JOB',
        'Psalmii' => 'PSA',
        'Proverbe' => 'PRO',
        'Eclesiastul' => 'ECC',
        'Cântarea Cântărilor' => 'SNG',
        'Isaia' => 'ISA',
        'Ieremia' => 'JER',
        'Plângerile lui Ieremia' => 'LAM',
        'Ezechiel' => 'EZK',
        'Daniel' => 'DAN',
        'Osea' => 'HOS',
        'Ioel' => 'JOL',
        'Amos' => 'AMO',
        'Obadia' => 'OBA',
        'Iona' => 'JON',
        'Mica' => 'MIC',
        'Naum' => 'NAM',
        'Habacuc' => 'HAB',
        'Țefania' => 'ZEP',
        'Hagai' => 'HAG',
        'Zaharia' => 'ZEC',
        'Maleahi' => 'MAL',
        'Matei' => 'MAT',
        'Marcu' => 'MRK',
        'Luca' => 'LUK',
        'Ioan' => 'JHN',
        'Faptele Apostolilor' => 'ACT',
        'Faptele' => 'ACT',
        'Romani' => 'ROM',
        '1 Corinteni' => '1CO',
        '2 Corinteni' => '2CO',
        'Galateni' => 'GAL',
        'Efeseni' => 'EPH',
        'Filipeni' => 'PHP',
        'Coloseni' => 'COL',
        '1 Tesaloniceni' => '1TH',
        '2 Tesaloniceni' => '2TH',
        '1 Timotei' => '1TI',
        '2 Timotei' => '2TI',
        'Tit' => 'TIT',
        'Filimon' => 'PHM',
        'Evrei' => 'HEB',
        'Iacov' => 'JAS',
        '1 Petru' => '1PE',
        '2 Petru' => '2PE',
        '1 Ioan' => '1JN',
        '2 Ioan' => '2JN',
        '3 Ioan' => '3JN',
        'Iuda' => 'JUD',
        'Apocalipsa' => 'REV',
    ];

    /**
     * Canonical abbreviation → primary localized name (used by `bookName()`).
     *
     * Curated separately because `NAME_TO_ABBREV` may contain multiple aliases
     * per book (e.g. `Faptele` and `Faptele Apostolilor` both map to `ACT`).
     *
     * @var array<string, string>
     */
    private const ABBREV_TO_NAME = [
        'GEN' => 'Geneza',
        'EXO' => 'Exodul',
        'LEV' => 'Leviticul',
        'NUM' => 'Numeri',
        'DEU' => 'Deuteronomul',
        'JOS' => 'Iosua',
        'JDG' => 'Judecători',
        'RUT' => 'Rut',
        '1SA' => '1 Samuel',
        '2SA' => '2 Samuel',
        '1KI' => '1 Împăraţi',
        '2KI' => '2 Împăraţi',
        '1CH' => '1 Cronici',
        '2CH' => '2 Cronici',
        'EZR' => 'Ezra',
        'NEH' => 'Neemia',
        'EST' => 'Estera',
        'JOB' => 'Iov',
        'PSA' => 'Psalmii',
        'PRO' => 'Proverbe',
        'ECC' => 'Eclesiastul',
        'SNG' => 'Cântarea Cântărilor',
        'ISA' => 'Isaia',
        'JER' => 'Ieremia',
        'LAM' => 'Plângerile lui Ieremia',
        'EZK' => 'Ezechiel',
        'DAN' => 'Daniel',
        'HOS' => 'Osea',
        'JOL' => 'Ioel',
        'AMO' => 'Amos',
        'OBA' => 'Obadia',
        'JON' => 'Iona',
        'MIC' => 'Mica',
        'NAM' => 'Naum',
        'HAB' => 'Habacuc',
        'ZEP' => 'Țefania',
        'HAG' => 'Hagai',
        'ZEC' => 'Zaharia',
        'MAL' => 'Maleahi',
        'MAT' => 'Matei',
        'MRK' => 'Marcu',
        'LUK' => 'Luca',
        'JHN' => 'Ioan',
        'ACT' => 'Faptele Apostolilor',
        'ROM' => 'Romani',
        '1CO' => '1 Corinteni',
        '2CO' => '2 Corinteni',
        'GAL' => 'Galateni',
        'EPH' => 'Efeseni',
        'PHP' => 'Filipeni',
        'COL' => 'Coloseni',
        '1TH' => '1 Tesaloniceni',
        '2TH' => '2 Tesaloniceni',
        '1TI' => '1 Timotei',
        '2TI' => '2 Timotei',
        'TIT' => 'Tit',
        'PHM' => 'Filimon',
        'HEB' => 'Evrei',
        'JAS' => 'Iacov',
        '1PE' => '1 Petru',
        '2PE' => '2 Petru',
        '1JN' => '1 Ioan',
        '2JN' => '2 Ioan',
        '3JN' => '3 Ioan',
        'JUD' => 'Iuda',
        'REV' => 'Apocalipsa',
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
        return '/(1 Cronici|1 Corinteni|1 Ioan|1 Împăraţi|1 Petru|1 Samuel|1 Tesaloniceni|1 Timotei|2 Cronici|2 Corinteni|2 Ioan|2 Împăraţi|2 Petru|2 Samuel|2 Tesaloniceni|2 Timotei|3 Ioan|Faptele Apostolilor|Amos|Coloseni|Daniel|Deuteronomul|Eclesiastul|Efeseni|Estera|Exodul|Ezechiel|Ezra|Galateni|Geneza|Habacuc|Hagai|Evrei|Osea|Isaia|Iacov|Judecători|Ieremia|Ioan|Iov|Ioel|Iona|Iosua|Iuda|Plângerile lui Ieremia|Leviticul|Luca|Maleahi|Matei|Mica|Marcu|Naum|Neemia|Numeri|Obadia|Filimon|Filipeni|Proverbe|Psalmii|Apocalipsa|Romani|Rut|Cântarea Cântărilor|Tit|Zaharia|Țefania) {1}([\d,:;-]+ *[\d,:-]+)/mi';
    }

    public function defaultVersion(): string
    {
        return 'VDC';
    }
}
