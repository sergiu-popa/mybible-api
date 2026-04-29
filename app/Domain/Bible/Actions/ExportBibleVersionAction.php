<?php

declare(strict_types=1);

namespace App\Domain\Bible\Actions;

use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Bible\Support\BibleCacheKeys;
use App\Domain\Bible\Support\BibleExportBuilder;
use App\Support\Caching\CachedRead;

final class ExportBibleVersionAction
{
    public function __construct(
        private readonly CachedRead $cache,
        private readonly BibleExportBuilder $builder,
    ) {}

    public function execute(BibleVersion $version): string
    {
        $builder = $this->builder;

        return $this->cache->read(
            BibleCacheKeys::versionExport($version->abbreviation),
            BibleCacheKeys::tagsForExport($version->abbreviation),
            86400,
            static fn (): string => $builder->build($version),
        );
    }
}
