<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\ReadingPlans\ListReadingPlansController;
use App\Http\Controllers\Api\V1\ReadingPlans\ShowReadingPlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('resolve-language')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', RegisterController::class)->name('register');
        Route::post('login', LoginController::class)->name('login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('logout', LogoutController::class)->name('logout');
            Route::get('me', MeController::class)->name('me');
        });
    });

    Route::prefix('reading-plans')->name('reading-plans.')->middleware('api-key')->group(function (): void {
        Route::get('/', ListReadingPlansController::class)->name('index');
        Route::get('{slug}', ShowReadingPlanController::class)->name('show');
    });
});
