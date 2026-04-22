<?php

declare(strict_types=1);

namespace App\Domain\Reference\Formatter\Languages;

use App\Domain\Reference\Exceptions\InvalidReferenceException;

final class HungarianFormatter implements LanguageFormatter
{
    /**
     * @var array<string, string>
     */
    private const NAME_TO_ABBREV = [
        '1Móz' => 'GEN',
        '1Mózes' => 'GEN',
        '2Móz' => 'EXO',
        '2Mózes' => 'EXO',
        '3Móz' => 'LEV',
        '3Mózes' => 'LEV',
        '4Móz' => 'NUM',
        '4Mózes' => 'NUM',
        '5Móz' => 'DEU',
        '5Mózes' => 'DEU',
        'Józs' => 'JOS',
        'Józsué' => 'JOS',
        'Bír' => 'JDG',
        'Bírák' => 'JDG',
        'Rut' => 'RUT',
        'Ruth' => 'RUT',
        '1Sám' => '1SA',
        '1Sámuel' => '1SA',
        '2Sám' => '2SA',
        '2Sámuel' => '2SA',
        '1Kir' => '1KI',
        '1Királyok' => '1KI',
        '2Kir' => '2KI',
        '2Királyok' => '2KI',
        '1Krón' => '1CH',
        '1Krónikák' => '1CH',
        '2Krón' => '2CH',
        '2Krónikák' => '2CH',
        'Ezsd' => 'EZR',
        'Ezsdrás' => 'EZR',
        'Neh' => 'NEH',
        'Nehémiás' => 'NEH',
        'Eszt' => 'EST',
        'Eszter' => 'EST',
        'Jób' => 'JOB',
        'Zsolt' => 'PSA',
        'Zsoltárok' => 'PSA',
        'Péld' => 'PRO',
        'Példabeszédek' => 'PRO',
        'Préd' => 'ECC',
        'Prédikátor' => 'ECC',
        'Én' => 'SNG',
        'Énekek éneke' => 'SNG',
        'Ézs' => 'ISA',
        'Ézsaiás' => 'ISA',
        'Jer' => 'JER',
        'Jeremiás' => 'JER',
        'JSir' => 'LAM',
        'Jeremiás siralmai' => 'LAM',
        'Ez' => 'EZK',
        'Ezékiel' => 'EZK',
        'Dán' => 'DAN',
        'Dániel' => 'DAN',
        'Hós' => 'HOS',
        'Hóseás' => 'HOS',
        'Joel' => 'JOL',
        'Jóel' => 'JOL',
        'Ám' => 'AMO',
        'Ámos' => 'AMO',
        'Abd' => 'OBA',
        'Abdiás' => 'OBA',
        'Jón' => 'JON',
        'Jónás' => 'JON',
        'Mik' => 'MIC',
        'Mikeás' => 'MIC',
        'Náh' => 'NAM',
        'Náhum' => 'NAM',
        'Hab' => 'HAB',
        'Habakuk' => 'HAB',
        'Sof' => 'ZEP',
        'Sofóniás' => 'ZEP',
        'Agg' => 'HAG',
        'Aggeus' => 'HAG',
        'Zak' => 'ZEC',
        'Zakariás' => 'ZEC',
        'Mal' => 'MAL',
        'Malakiás' => 'MAL',
        'Mt' => 'MAT',
        'Máté' => 'MAT',
        'Mk' => 'MRK',
        'Márk' => 'MRK',
        'Lk' => 'LUK',
        'Lukács' => 'LUK',
        'Jn' => 'JHN',
        'János' => 'JHN',
        'Csel' => 'ACT',
        'Apcsel' => 'ACT',
        'Róm' => 'ROM',
        'Róma' => 'ROM',
        '1Kor' => '1CO',
        '1Korintus' => '1CO',
        '2Kor' => '2CO',
        '2Korintus' => '2CO',
        'Gal' => 'GAL',
        'Galata' => 'GAL',
        'Ef' => 'EPH',
        'Efézusi' => 'EPH',
        'Fil' => 'PHP',
        'Filippi' => 'PHP',
        'Kol' => 'COL',
        'Kolosse' => 'COL',
        '1Thess' => '1TH',
        '1Tesszalonika' => '1TH',
        '2Thess' => '2TH',
        '2Tesszalonika' => '2TH',
        '1Tim' => '1TI',
        '1Timóteus' => '1TI',
        '1Timótheus' => '1TI',
        '2Tim' => '2TI',
        '2Timóteus' => '2TI',
        '2Timótheus' => '2TI',
        'Tit' => 'TIT',
        'Titus' => 'TIT',
        'Filem' => 'PHM',
        'Filemon' => 'PHM',
        'Zsid' => 'HEB',
        'Zsidó' => 'HEB',
        'Jak' => 'JAS',
        'Jakab' => 'JAS',
        '1Pét' => '1PE',
        '1Péter' => '1PE',
        '2Pét' => '2PE',
        '2Péter' => '2PE',
        '1Ján' => '1JN',
        '1János' => '1JN',
        '2Ján' => '2JN',
        '2János' => '2JN',
        '3Ján' => '3JN',
        '3János' => '3JN',
        'Júd' => 'JUD',
        'Júdás' => 'JUD',
        'Jel' => 'REV',
        'Jelenések' => 'REV',
    ];

    /**
     * @var array<string, string>
     */
    private const ABBREV_TO_NAME = [
        'GEN' => '1Mózes',
        'EXO' => '2Mózes',
        'LEV' => '3Mózes',
        'NUM' => '4Mózes',
        'DEU' => '5Mózes',
        'JOS' => 'Józsué',
        'JDG' => 'Bírák',
        'RUT' => 'Ruth',
        '1SA' => '1Sámuel',
        '2SA' => '2Sámuel',
        '1KI' => '1Királyok',
        '2KI' => '2Királyok',
        '1CH' => '1Krónikák',
        '2CH' => '2Krónikák',
        'EZR' => 'Ezsdrás',
        'NEH' => 'Nehémiás',
        'EST' => 'Eszter',
        'JOB' => 'Jób',
        'PSA' => 'Zsoltárok',
        'PRO' => 'Példabeszédek',
        'ECC' => 'Prédikátor',
        'SNG' => 'Énekek éneke',
        'ISA' => 'Ézsaiás',
        'JER' => 'Jeremiás',
        'LAM' => 'Jeremiás siralmai',
        'EZK' => 'Ezékiel',
        'DAN' => 'Dániel',
        'HOS' => 'Hóseás',
        'JOL' => 'Jóel',
        'AMO' => 'Ámos',
        'OBA' => 'Abdiás',
        'JON' => 'Jónás',
        'MIC' => 'Mikeás',
        'NAM' => 'Náhum',
        'HAB' => 'Habakuk',
        'ZEP' => 'Sofóniás',
        'HAG' => 'Aggeus',
        'ZEC' => 'Zakariás',
        'MAL' => 'Malakiás',
        'MAT' => 'Máté',
        'MRK' => 'Márk',
        'LUK' => 'Lukács',
        'JHN' => 'János',
        'ACT' => 'Apcsel',
        'ROM' => 'Róma',
        '1CO' => '1Korintus',
        '2CO' => '2Korintus',
        'GAL' => 'Galata',
        'EPH' => 'Efézusi',
        'PHP' => 'Filippi',
        'COL' => 'Kolosse',
        '1TH' => '1Tesszalonika',
        '2TH' => '2Tesszalonika',
        '1TI' => '1Timóteus',
        '2TI' => '2Timóteus',
        'TIT' => 'Titus',
        'PHM' => 'Filemon',
        'HEB' => 'Zsidó',
        'JAS' => 'Jakab',
        '1PE' => '1Péter',
        '2PE' => '2Péter',
        '1JN' => '1János',
        '2JN' => '2János',
        '3JN' => '3János',
        'JUD' => 'Júdás',
        'REV' => 'Jelenések',
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
        return '/(1Móz|1Mózes|2Móz|2Mózes|3Móz|3Mózes|4Móz|4Mózes|5Móz|5Mózes|Józs|Józsué|Bír|Bírák|Rut|Ruth|1Sám|1Sámuel|2Sám|2Sámuel|1Kir|1Királyok|2Kir|2Királyok|1Krón|1Krónikák|2Krón|2Krónikák|Ezsd|Ezsdrás|Neh|Nehémiás|Eszt|Eszter|Jób|Zsolt|Zsoltárok|Péld|Példabeszédek|Préd|Prédikátor|Én|Énekek éneke|Ézs|Ézsaiás|Jer|Jeremiás|JSir|Jeremiás siralmai|Ez|Ezékiel|Dán|Dániel|Hós|Hóseás|Joel|Jóel|Ám|Ámos|Abd|Abdiás|Jón|Jónás|Mik|Mikeás|Náh|Náhum|Hab|Habakuk|Sof|Sofóniás|Agg|Aggeus|Zak|Zakariás|Mal|Malakiás|Mt|Máté|Mk|Márk|Lk|Lukács|Jn|János|Csel|Apcsel|Róm|Róma|1Kor|1Korintus|2Kor|2Korintus|Gal|Galata|Ef|Efézusi|Fil|Filippi|Kol|Kolosse|1Thess|1Tesszalonika|2Thess|2Tesszalonika|1Tim|1Timóteus|1Timótheus|2Tim|2Timóteus|2Timótheus|Tit|Titus|Filem|Filemon|Zsid|Zsidó|Jak|Jakab|1Pét|1Péter|2Pét|2Péter|1Ján|1János|2Ján|2János|3Ján|3János|Júd|Júdás|Jel|Jelenések) {1}([\d,:;-]+ *[\d,:-]+)/mi';
    }

    public function defaultVersion(): string
    {
        return 'KAR';
    }
}
