<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile App Version Metadata
    |--------------------------------------------------------------------------
    |
    | Per-platform minimum/latest version and store update URLs exposed via
    | `GET /api/v1/mobile/version`. Field names are locked by the existing
    | mobile client contract — do not rename.
    |
    */

    'ios' => [
        'minimum_supported_version' => env('MOBILE_IOS_MIN', '3.0.0'),
        'latest_version' => env('MOBILE_IOS_LATEST', '3.4.1'),
        'update_url' => env('MOBILE_IOS_URL', ''),
        'force_update_below' => env('MOBILE_IOS_FORCE', '3.0.0'),
    ],

    'android' => [
        'minimum_supported_version' => env('MOBILE_ANDROID_MIN', '3.0.0'),
        'latest_version' => env('MOBILE_ANDROID_LATEST', '3.4.1'),
        'update_url' => env('MOBILE_ANDROID_URL', ''),
        'force_update_below' => env('MOBILE_ANDROID_FORCE', '3.0.0'),
    ],

    'bootstrap' => [
        'cache_ttl' => (int) env('BOOTSTRAP_CACHE_TTL', 300),
    ],

];
