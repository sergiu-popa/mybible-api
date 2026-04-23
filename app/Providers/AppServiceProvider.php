<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Notes\Models\Note;
use App\Domain\Favorites\Models\Favorite;
use App\Domain\Favorites\Models\FavoriteCategory;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Policies\NotePolicy;
use App\Policies\FavoriteCategoryPolicy;
use App\Policies\FavoritePolicy;
use App\Policies\ReadingPlanSubscriptionPolicy;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
    }
}
