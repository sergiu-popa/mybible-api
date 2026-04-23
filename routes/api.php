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
use App\Http\Controllers\Api\V1\Favorites\CreateFavoriteCategoryController;
use App\Http\Controllers\Api\V1\Favorites\CreateFavoriteController;
use App\Http\Controllers\Api\V1\Favorites\DeleteFavoriteCategoryController;
use App\Http\Controllers\Api\V1\Favorites\DeleteFavoriteController;
use App\Http\Controllers\Api\V1\Favorites\ListFavoriteCategoriesController;
use App\Http\Controllers\Api\V1\Favorites\ListFavoritesController;
use App\Http\Controllers\Api\V1\Favorites\UpdateFavoriteCategoryController;
use App\Http\Controllers\Api\V1\Favorites\UpdateFavoriteController;
use App\Http\Controllers\Api\V1\Notes\DeleteNoteController;
use App\Http\Controllers\Api\V1\Notes\ListNotesController;
use App\Http\Controllers\Api\V1\Notes\StoreNoteController;
use App\Http\Controllers\Api\V1\Notes\UpdateNoteController;
use App\Http\Controllers\Api\V1\ReadingPlans\AbandonReadingPlanSubscriptionController;
use App\Http\Controllers\Api\V1\ReadingPlans\CompleteReadingPlanSubscriptionDayController;
use App\Http\Controllers\Api\V1\ReadingPlans\FinishReadingPlanSubscriptionController;
use App\Http\Controllers\Api\V1\ReadingPlans\ListReadingPlansController;
use App\Http\Controllers\Api\V1\ReadingPlans\RescheduleReadingPlanSubscriptionController;
use App\Http\Controllers\Api\V1\ReadingPlans\ShowReadingPlanController;
use App\Http\Controllers\Api\V1\ReadingPlans\StartReadingPlanSubscriptionController;
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
});
