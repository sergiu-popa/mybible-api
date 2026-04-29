<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Caching;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

final class ClearCacheTagCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::store()->clear();
    }

    public function test_it_flushes_only_the_named_tag(): void
    {
        Cache::tags(['target'])->put('a', 'a-value', 600);
        Cache::tags(['target'])->put('b', 'b-value', 600);
        Cache::tags(['sibling'])->put('c', 'c-value', 600);

        $exit = Artisan::call('mybible:cache-clear-tag', ['tag' => 'target']);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertNull(Cache::tags(['target'])->get('a'));
        $this->assertNull(Cache::tags(['target'])->get('b'));
        $this->assertSame('c-value', Cache::tags(['sibling'])->get('c'));
    }

    public function test_dry_run_does_not_flush(): void
    {
        Cache::tags(['target'])->put('a', 'a-value', 600);

        $exit = Artisan::call('mybible:cache-clear-tag', ['tag' => 'target', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertStringContainsString('[dry-run] Would flush cache tag: target', Artisan::output());
        $this->assertSame('a-value', Cache::tags(['target'])->get('a'));
    }

    public function test_an_empty_tag_is_rejected(): void
    {
        $exit = Artisan::call('mybible:cache-clear-tag', ['tag' => '']);

        $this->assertSame(Command::INVALID, $exit);
    }
}
