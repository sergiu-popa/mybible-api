<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Media Disk
    |--------------------------------------------------------------------------
    |
    | Filesystem disk used to resolve absolute URLs for educational resource
    | thumbnails and media files. Defaults to `public` for local development;
    | prod flips the env var to `s3` at the MBA-020 cutover. The configured
    | disk name is passed to `Storage::disk(...)` which must exist in
    | `config/filesystems.php`.
    |
    */

    'media_disk' => env('EDUCATIONAL_RESOURCES_DISK', 'public'),

];
