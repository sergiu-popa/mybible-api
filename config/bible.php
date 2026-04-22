<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Bible Version by Language
    |--------------------------------------------------------------------------
    |
    | Fallback version abbreviation used when a verse lookup is made without
    | an explicit ?version= parameter and the requester has no preferred
    | version on their profile. Keyed by the resolved request language.
    |
    */

    'default_version_by_language' => [
        'ro' => 'VDC',
        'en' => 'KJV',
        'hu' => 'KAR',
    ],

];
