<?php

declare(strict_types=1);

namespace App\Support\Caching;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Manual cache invalidation for a single tag. Used by ops + deploy hooks
 * (the future admin/import write Actions call `Cache::tags(...)->flush()`
 * directly through the per-domain `tagsFor*()` helpers).
 *
 * Examples:
 *
 *     php artisan mybible:cache-clear-tag ss:lesson:42
 *     php artisan mybible:cache-clear-tag bible:versions --dry-run
 */
final class ClearCacheTagCommand extends Command
{
    protected $signature = 'mybible:cache-clear-tag
        {tag : The cache tag to flush (e.g. ss:lessons, bible:versions, dev:ro:morning).}
        {--dry-run : Report the intent without flushing.}';

    protected $description = 'Flush every cache entry stored under the given tag.';

    public function handle(): int
    {
        $tag = (string) $this->argument('tag');
        $dryRun = (bool) $this->option('dry-run');

        if ($tag === '') {
            $this->error('Tag must not be empty.');

            return self::INVALID;
        }

        if ($dryRun) {
            $this->line("[dry-run] Would flush cache tag: {$tag}");

            return self::SUCCESS;
        }

        Cache::tags([$tag])->flush();

        Log::info('cache.tag_flushed', ['tag' => $tag]);
        $this->line("Flushed cache tag: {$tag}");

        return self::SUCCESS;
    }
}
