<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Analytics\Models\ResourceDownload;
use App\Domain\EducationalResources\Models\EducationalResource;
use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Models\ResourceBookChapter;
use App\Domain\Favorites\Models\Favorite;
use App\Domain\Favorites\Models\FavoriteCategory;
use App\Domain\Notes\Models\Note;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Domain\Sync\Actions\ShowUserSyncAction;
use App\Domain\Sync\Sync\Builders\DevotionalFavoriteSyncBuilder;
use App\Domain\Sync\Sync\Builders\FavoriteSyncBuilder;
use App\Domain\Sync\Sync\Builders\HymnalFavoriteSyncBuilder;
use App\Domain\Sync\Sync\Builders\NoteSyncBuilder;
use App\Domain\Sync\Sync\Builders\SabbathSchoolAnswerSyncBuilder;
use App\Domain\Sync\Sync\Builders\SabbathSchoolFavoriteSyncBuilder;
use App\Domain\Sync\Sync\Builders\SabbathSchoolHighlightSyncBuilder;
use App\Policies\FavoriteCategoryPolicy;
use App\Policies\FavoritePolicy;
use App\Policies\NotePolicy;
use App\Policies\ReadingPlanSubscriptionPolicy;
use App\Support\Caching\CacheStoreGuard;
use App\Support\Caching\ClearCacheTagCommand;
use App\Support\Observability\SlowQueryListener;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $builderClasses = [
            FavoriteSyncBuilder::class,
            NoteSyncBuilder::class,
            SabbathSchoolAnswerSyncBuilder::class,
            SabbathSchoolHighlightSyncBuilder::class,
            SabbathSchoolFavoriteSyncBuilder::class,
            DevotionalFavoriteSyncBuilder::class,
            HymnalFavoriteSyncBuilder::class,
        ];

        foreach ($builderClasses as $class) {
            $this->app->tag($class, 'sync.builder');
        }

        $this->app->bind(ShowUserSyncAction::class, function ($app): ShowUserSyncAction {
            return new ShowUserSyncAction($app->tagged('sync.builder'));
        });

        if (class_exists(Scramble::class)) {
            Scramble::ignoreDefaultRoutes();
            Scramble::resolveTagsUsing(function (RouteInfo $routeInfo): array {
                $defaultTag = Str::of(class_basename($routeInfo->className() ?: ''))
                    ->replace('Controller', '')
                    ->toString();

                $classPhpDoc = $routeInfo->isClassBased()
                    ? $routeInfo->reflectionMethod()?->getDeclaringClass()->getDocComment()
                    : null;

                if (is_string($classPhpDoc) && preg_match('/@tags\s+([^\n*]+)/', $classPhpDoc, $matches) === 1) {
                    return array_values(array_filter(array_map('trim', explode(',', $matches[1]))));
                }

                return [$defaultTag];
            });
        }
    }

    public function boot(): void
    {
        Gate::policy(ReadingPlanSubscription::class, ReadingPlanSubscriptionPolicy::class);
        Gate::policy(Note::class, NotePolicy::class);
        Gate::policy(Favorite::class, FavoritePolicy::class);
        Gate::policy(FavoriteCategory::class, FavoriteCategoryPolicy::class);

        // Shared password policy for every user-supplied new password
        // (registration, reset, change). Keeping this in one place avoids
        // drift between the three entry points. Symbols are intentionally
        // omitted so the policy does not break existing onboarding flows
        // that require only `min 8, mixed case, number`.
        Password::defaults(function (): Password {
            return Password::min(8)->mixedCase()->numbers();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([ClearCacheTagCommand::class]);
        }

        // Tag-based invalidation is load-bearing across cached read endpoints.
        // Fail at boot if the configured store cannot support tags so a
        // misconfigured CACHE_STORE surfaces on deploy, not at the first
        // write-side flush. Skipped during testing because PHPUnit may swap
        // drivers per-test (e.g. forcing 'database' to assert the guard).
        if (! $this->app->environment('testing')) {
            CacheStoreGuard::ensureTaggable();
        }

        RateLimiter::for('public-anon', static function (Request $request): Limit {
            return Limit::perMinute(180)->by($request->ip());
        });

        RateLimiter::for('per-user', static function (Request $request): Limit {
            $key = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

            return Limit::perMinute(300)->by($key);
        });

        // Per (ip, device_id) bucket so corporate NAT does not throttle
        // hundreds of legitimate users sharing one egress IP. Falls back
        // to ip-only when no device_id was sent.
        RateLimiter::for('downloads', static function (Request $request): Limit {
            $deviceHeader = $request->header('X-Device-Id');
            $deviceBody = $request->input('device_id');
            $deviceId = is_string($deviceHeader) && $deviceHeader !== ''
                ? $deviceHeader
                : (is_string($deviceBody) && $deviceBody !== '' ? $deviceBody : 'no-device');

            return Limit::perMinute(60)->by(sprintf('%s|%s', (string) $request->ip(), $deviceId));
        });

        // Aliases for the three downloadable morph targets so legacy
        // rows survive namespace moves. Other polymorphic relations
        // (Sanctum tokenables) keep falling back to FQCN, so we use
        // morphMap (not enforceMorphMap) to avoid forcing every model
        // in the app to register an alias.
        Relation::morphMap([
            ResourceDownload::TYPE_EDUCATIONAL_RESOURCE => EducationalResource::class,
            ResourceDownload::TYPE_RESOURCE_BOOK => ResourceBook::class,
            ResourceDownload::TYPE_RESOURCE_BOOK_CHAPTER => ResourceBookChapter::class,
        ]);

        if (! $this->app->environment('local', 'testing')) {
            SlowQueryListener::register();
        }
    }
}
