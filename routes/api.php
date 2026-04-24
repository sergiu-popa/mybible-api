<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\RequestPasswordResetController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Bible\ExportBibleVersionController;
use App\Http\Controllers\Api\V1\Bible\ListBibleBookChaptersController;
use App\Http\Controllers\Api\V1\Bible\ListBibleBooksController;
use App\Http\Controllers\Api\V1\Bible\ListBibleVersionsController;
use App\Http\Controllers\Api\V1\Collections\ListCollectionTopicsController;
use App\Http\Controllers\Api\V1\Collections\ShowCollectionTopicController;
use App\Http\Controllers\Api\V1\Devotionals\ListDevotionalArchiveController;
use App\Http\Controllers\Api\V1\Devotionals\ListDevotionalFavoritesController;
use App\Http\Controllers\Api\V1\Devotionals\ShowDevotionalController;
use App\Http\Controllers\Api\V1\Devotionals\ToggleDevotionalFavoriteController;
use App\Http\Controllers\Api\V1\EducationalResources\ListResourceCategoriesController;
use App\Http\Controllers\Api\V1\EducationalResources\ListResourcesByCategoryController;
use App\Http\Controllers\Api\V1\EducationalResources\ShowEducationalResourceController;
use App\Http\Controllers\Api\V1\Favorites\CreateFavoriteCategoryController;
use App\Http\Controllers\Api\V1\Favorites\CreateFavoriteController;
use App\Http\Controllers\Api\V1\Favorites\DeleteFavoriteCategoryController;
use App\Http\Controllers\Api\V1\Favorites\DeleteFavoriteController;
use App\Http\Controllers\Api\V1\Favorites\ListFavoriteCategoriesController;
use App\Http\Controllers\Api\V1\Favorites\ListFavoritesController;
use App\Http\Controllers\Api\V1\Favorites\UpdateFavoriteCategoryController;
use App\Http\Controllers\Api\V1\Favorites\UpdateFavoriteController;
use App\Http\Controllers\Api\V1\Hymnal\ListHymnalBooksController;
use App\Http\Controllers\Api\V1\Hymnal\ListHymnalBookSongsController;
use App\Http\Controllers\Api\V1\Hymnal\ListHymnalFavoritesController;
use App\Http\Controllers\Api\V1\Hymnal\ShowHymnalSongController;
use App\Http\Controllers\Api\V1\Hymnal\ToggleHymnalFavoriteController;
use App\Http\Controllers\Api\V1\Notes\DeleteNoteController;
use App\Http\Controllers\Api\V1\Notes\ListNotesController;
use App\Http\Controllers\Api\V1\Notes\StoreNoteController;
use App\Http\Controllers\Api\V1\Notes\UpdateNoteController;
use App\Http\Controllers\Api\V1\Olympiad\ListOlympiadThemesController;
use App\Http\Controllers\Api\V1\Olympiad\ShowOlympiadThemeController;
use App\Http\Controllers\Api\V1\ReadingPlans\AbandonReadingPlanSubscriptionController;
use App\Http\Controllers\Api\V1\ReadingPlans\CompleteReadingPlanSubscriptionDayController;
use App\Http\Controllers\Api\V1\ReadingPlans\FinishReadingPlanSubscriptionController;
use App\Http\Controllers\Api\V1\ReadingPlans\ListReadingPlansController;
use App\Http\Controllers\Api\V1\ReadingPlans\RescheduleReadingPlanSubscriptionController;
use App\Http\Controllers\Api\V1\ReadingPlans\ShowReadingPlanController;
use App\Http\Controllers\Api\V1\ReadingPlans\StartReadingPlanSubscriptionController;
use App\Http\Controllers\Api\V1\SabbathSchool\DeleteSabbathSchoolAnswerController;
use App\Http\Controllers\Api\V1\SabbathSchool\ListSabbathSchoolFavoritesController;
use App\Http\Controllers\Api\V1\SabbathSchool\ListSabbathSchoolHighlightsController;
use App\Http\Controllers\Api\V1\SabbathSchool\ListSabbathSchoolLessonsController;
use App\Http\Controllers\Api\V1\SabbathSchool\ShowSabbathSchoolAnswerController;
use App\Http\Controllers\Api\V1\SabbathSchool\ShowSabbathSchoolLessonController;
use App\Http\Controllers\Api\V1\SabbathSchool\ToggleSabbathSchoolFavoriteController;
use App\Http\Controllers\Api\V1\SabbathSchool\ToggleSabbathSchoolHighlightController;
use App\Http\Controllers\Api\V1\SabbathSchool\UpsertSabbathSchoolAnswerController;
use App\Http\Controllers\Api\V1\Verses\GetDailyVerseController;
use App\Http\Controllers\Api\V1\Verses\ResolveVersesController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', RegisterController::class)->name('register');
        Route::post('login', LoginController::class)->name('login');
        Route::post('forgot-password', RequestPasswordResetController::class)->name('forgot-password');
        Route::post('reset-password', ResetPasswordController::class)->name('reset-password');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('logout', LogoutController::class)->name('logout');
            Route::get('me', MeController::class)->name('me');
        });
    });

    Route::prefix('bible-versions')
        ->name('bible-versions.')
        ->middleware('api-key-or-sanctum')
        ->group(function (): void {
            Route::get('/', ListBibleVersionsController::class)
                ->middleware('resolve-language')
                ->name('index');
            Route::get('{version:abbreviation}/export', ExportBibleVersionController::class)->name('export');
        });

    Route::prefix('books')
        ->name('books.')
        ->middleware('api-key-or-sanctum')
        ->group(function (): void {
            Route::get('/', ListBibleBooksController::class)
                ->middleware('resolve-language')
                ->name('index');
            Route::get('{book:abbreviation}/chapters', ListBibleBookChaptersController::class)->name('chapters');
        });

    Route::middleware(['api-key-or-sanctum', 'resolve-language'])->group(function (): void {
        Route::get('verses', ResolveVersesController::class)->name('verses.index');
        Route::get('daily-verse', GetDailyVerseController::class)->name('daily-verse.show');
    });

    Route::prefix('collections')
        ->name('collections.')
        ->middleware(['api-key-or-sanctum', 'resolve-language'])
        ->group(function (): void {
            Route::get('/', ListCollectionTopicsController::class)->name('index');
            Route::get('{topic}', ShowCollectionTopicController::class)->name('show');
        });

    Route::prefix('reading-plans')
        ->name('reading-plans.')
        ->middleware(['api-key-or-sanctum', 'resolve-language'])
        ->group(function (): void {
            Route::get('/', ListReadingPlansController::class)->name('index');
            Route::get('{plan:slug}', ShowReadingPlanController::class)->name('show');
            Route::post('{plan:slug}/subscriptions', StartReadingPlanSubscriptionController::class)
                ->middleware('auth:sanctum')
                ->name('subscriptions.store');
        });

    Route::prefix('olympiad')
        ->name('olympiad.')
        ->middleware(['api-key-or-sanctum', 'resolve-language'])
        ->group(function (): void {
            Route::get('themes', ListOlympiadThemesController::class)->name('themes.index');
            Route::get('themes/{book}/{chapters}', ShowOlympiadThemeController::class)
                ->where('book', '[A-Za-z0-9]+')
                ->name('themes.show');
        });

    Route::middleware(['api-key-or-sanctum', 'resolve-language', 'cache.headers:public;max_age=3600;etag'])
        ->group(function (): void {
            Route::get('hymnal-books', ListHymnalBooksController::class)
                ->name('hymnal-books.index');
            Route::get('hymnal-books/{book:slug}/songs', ListHymnalBookSongsController::class)
                ->name('hymnal-books.songs');
            Route::get('hymnal-songs/{song}', ShowHymnalSongController::class)
                ->name('hymnal-songs.show');
        });

    Route::middleware('auth:sanctum')
        ->prefix('hymnal-favorites')
        ->name('hymnal-favorites.')
        ->group(function (): void {
            Route::get('/', ListHymnalFavoritesController::class)->name('index');
            Route::post('toggle', ToggleHymnalFavoriteController::class)->name('toggle');
        });

    Route::prefix('devotionals')
        ->name('devotionals.')
        ->middleware(['api-key-or-sanctum', 'resolve-language'])
        ->group(function (): void {
            // Register `/archive` before the root show route so a future
            // `{devotional}` segment cannot shadow it.
            Route::get('archive', ListDevotionalArchiveController::class)->name('archive');
            Route::get('/', ShowDevotionalController::class)->name('show');
        });

    Route::prefix('devotional-favorites')
        ->name('devotional-favorites.')
        ->middleware('auth:sanctum')
        ->group(function (): void {
            Route::get('/', ListDevotionalFavoritesController::class)->name('index');
            Route::post('toggle', ToggleDevotionalFavoriteController::class)->name('toggle');
        });

    Route::middleware('auth:sanctum')
        ->prefix('notes')
        ->name('notes.')
        ->group(function (): void {
            Route::get('/', ListNotesController::class)->name('index');
            Route::post('/', StoreNoteController::class)->name('store');
            Route::patch('{note}', UpdateNoteController::class)->name('update');
            Route::delete('{note}', DeleteNoteController::class)->name('destroy');
        });

    Route::middleware(['auth:sanctum', 'resolve-language'])
        ->prefix('favorite-categories')
        ->name('favorite-categories.')
        ->group(function (): void {
            Route::get('/', ListFavoriteCategoriesController::class)->name('index');
            Route::post('/', CreateFavoriteCategoryController::class)->name('store');
            Route::patch('{category}', UpdateFavoriteCategoryController::class)->name('update');
            Route::delete('{category}', DeleteFavoriteCategoryController::class)->name('destroy');
        });

    Route::middleware(['auth:sanctum', 'resolve-language'])
        ->prefix('favorites')
        ->name('favorites.')
        ->group(function (): void {
            Route::get('/', ListFavoritesController::class)->name('index');
            Route::post('/', CreateFavoriteController::class)->name('store');
            Route::patch('{favorite}', UpdateFavoriteController::class)->name('update');
            Route::delete('{favorite}', DeleteFavoriteController::class)->name('destroy');
        });

    Route::middleware(['api-key-or-sanctum', 'resolve-language'])->group(function (): void {
        Route::get('resource-categories', ListResourceCategoriesController::class)
            ->name('resource-categories.index');
        Route::get('resource-categories/{category}/resources', ListResourcesByCategoryController::class)
            ->name('resource-categories.resources.index');
        Route::get('resources/{resource:uuid}', ShowEducationalResourceController::class)
            ->name('resources.show');
    });

    Route::middleware('auth:sanctum')
        ->prefix('reading-plan-subscriptions')
        ->name('reading-plan-subscriptions.')
        ->scopeBindings()
        ->group(function (): void {
            Route::post('{subscription}/days/{day}/complete', CompleteReadingPlanSubscriptionDayController::class)
                ->name('days.complete');
            Route::patch('{subscription}/start-date', RescheduleReadingPlanSubscriptionController::class)
                ->name('reschedule');
            Route::post('{subscription}/finish', FinishReadingPlanSubscriptionController::class)
                ->name('finish');
            Route::post('{subscription}/abandon', AbandonReadingPlanSubscriptionController::class)
                ->name('abandon');
        });

    Route::prefix('sabbath-school')
        ->name('sabbath-school.')
        ->group(function (): void {
            Route::middleware(['api-key-or-sanctum', 'resolve-language'])->group(function (): void {
                Route::get('lessons', ListSabbathSchoolLessonsController::class)
                    ->name('lessons.index');
                Route::get('lessons/{lesson}', ShowSabbathSchoolLessonController::class)
                    ->name('lessons.show');
            });

            Route::middleware('auth:sanctum')->group(function (): void {
                Route::get('questions/{question}/answer', ShowSabbathSchoolAnswerController::class)
                    ->name('answers.show');
                Route::post('questions/{question}/answer', UpsertSabbathSchoolAnswerController::class)
                    ->name('answers.upsert');
                Route::delete('questions/{question}/answer', DeleteSabbathSchoolAnswerController::class)
                    ->name('answers.destroy');

                Route::get('highlights', ListSabbathSchoolHighlightsController::class)
                    ->name('highlights.index');
                Route::post('highlights/toggle', ToggleSabbathSchoolHighlightController::class)
                    ->name('highlights.toggle');

                Route::get('favorites', ListSabbathSchoolFavoritesController::class)
                    ->name('favorites.index');
                Route::post('favorites/toggle', ToggleSabbathSchoolFavoriteController::class)
                    ->name('favorites.toggle');
            });
        });
});
