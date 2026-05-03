<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\Collections\CreateCollectionController;
use App\Http\Controllers\Api\V1\Admin\Collections\CreateCollectionTopicController;
use App\Http\Controllers\Api\V1\Admin\Collections\DeleteCollectionController;
use App\Http\Controllers\Api\V1\Admin\Collections\DeleteCollectionTopicController;
use App\Http\Controllers\Api\V1\Admin\Collections\ListAdminCollectionsController;
use App\Http\Controllers\Api\V1\Admin\Collections\ListAdminCollectionTopicsController;
use App\Http\Controllers\Api\V1\Admin\Collections\UpdateCollectionController;
use App\Http\Controllers\Api\V1\Admin\Collections\UpdateCollectionTopicController;
use App\Http\Controllers\Api\V1\Admin\Commentary\CreateCommentaryController;
use App\Http\Controllers\Api\V1\Admin\Commentary\CreateCommentaryTextController;
use App\Http\Controllers\Api\V1\Admin\Commentary\DeleteCommentaryTextController;
use App\Http\Controllers\Api\V1\Admin\Commentary\ListCommentariesController;
use App\Http\Controllers\Api\V1\Admin\Commentary\ListCommentaryTextsController;
use App\Http\Controllers\Api\V1\Admin\Commentary\PublishCommentaryController;
use App\Http\Controllers\Api\V1\Admin\Commentary\ReorderCommentaryTextsController;
use App\Http\Controllers\Api\V1\Admin\Commentary\UnpublishCommentaryController;
use App\Http\Controllers\Api\V1\Admin\Commentary\UpdateCommentaryController;
use App\Http\Controllers\Api\V1\Admin\Commentary\UpdateCommentaryTextController;
use App\Http\Controllers\Api\V1\Admin\Devotionals\CreateDevotionalTypeController;
use App\Http\Controllers\Api\V1\Admin\Devotionals\DeleteDevotionalTypeController;
use App\Http\Controllers\Api\V1\Admin\Devotionals\ListDevotionalTypesController;
use App\Http\Controllers\Api\V1\Admin\Devotionals\ReorderDevotionalTypesController;
use App\Http\Controllers\Api\V1\Admin\Devotionals\UpdateDevotionalTypeController;
use App\Http\Controllers\Api\V1\Admin\EducationalResources\CreateResourceBookChapterController as AdminCreateResourceBookChapterController;
use App\Http\Controllers\Api\V1\Admin\EducationalResources\CreateResourceBookController as AdminCreateResourceBookController;
use App\Http\Controllers\Api\V1\Admin\EducationalResources\DeleteResourceBookChapterController as AdminDeleteResourceBookChapterController;
use App\Http\Controllers\Api\V1\Admin\EducationalResources\DeleteResourceBookController as AdminDeleteResourceBookController;
use App\Http\Controllers\Api\V1\Admin\EducationalResources\ListResourceBookChaptersController as AdminListResourceBookChaptersController;
use App\Http\Controllers\Api\V1\Admin\EducationalResources\ListResourceBooksController as AdminListResourceBooksController;
use App\Http\Controllers\Api\V1\Admin\EducationalResources\PublishResourceBookController as AdminPublishResourceBookController;
use App\Http\Controllers\Api\V1\Admin\EducationalResources\ReorderEducationalResourcesController;
use App\Http\Controllers\Api\V1\Admin\EducationalResources\ReorderResourceBookChaptersController as AdminReorderResourceBookChaptersController;
use App\Http\Controllers\Api\V1\Admin\EducationalResources\ReorderResourceBooksController as AdminReorderResourceBooksController;
use App\Http\Controllers\Api\V1\Admin\EducationalResources\ReorderResourceCategoriesController;
use App\Http\Controllers\Api\V1\Admin\EducationalResources\ShowResourceDownloadsSummaryController as AdminShowResourceDownloadsSummaryController;
use App\Http\Controllers\Api\V1\Admin\EducationalResources\UnpublishResourceBookController as AdminUnpublishResourceBookController;
use App\Http\Controllers\Api\V1\Admin\EducationalResources\UpdateResourceBookChapterController as AdminUpdateResourceBookChapterController;
use App\Http\Controllers\Api\V1\Admin\EducationalResources\UpdateResourceBookController as AdminUpdateResourceBookController;
use App\Http\Controllers\Api\V1\Admin\Imports\ShowImportJobController;
use App\Http\Controllers\Api\V1\Admin\Mobile\CreateMobileVersionController;
use App\Http\Controllers\Api\V1\Admin\Mobile\DeleteMobileVersionController;
use App\Http\Controllers\Api\V1\Admin\Mobile\ListMobileVersionsController;
use App\Http\Controllers\Api\V1\Admin\Mobile\UpdateMobileVersionController;
use App\Http\Controllers\Api\V1\Admin\Olympiad\ListAdminOlympiadAttemptsController;
use App\Http\Controllers\Api\V1\Admin\Olympiad\ReorderOlympiadQuestionsController;
use App\Http\Controllers\Api\V1\Admin\QrCode\CreateQrCodeController as AdminCreateQrCodeController;
use App\Http\Controllers\Api\V1\Admin\QrCode\DeleteQrCodeController as AdminDeleteQrCodeController;
use App\Http\Controllers\Api\V1\Admin\QrCode\ListAdminQrCodesController;
use App\Http\Controllers\Api\V1\Admin\QrCode\UpdateQrCodeController as AdminUpdateQrCodeController;
use App\Http\Controllers\Api\V1\Admin\References\ValidateReferenceController;
use App\Http\Controllers\Api\V1\Admin\SabbathSchool\CreateLessonController as AdminCreateSabbathSchoolLessonController;
use App\Http\Controllers\Api\V1\Admin\SabbathSchool\CreateSegmentContentController as AdminCreateSabbathSchoolSegmentContentController;
use App\Http\Controllers\Api\V1\Admin\SabbathSchool\CreateTrimesterController as AdminCreateSabbathSchoolTrimesterController;
use App\Http\Controllers\Api\V1\Admin\SabbathSchool\DeleteLessonController as AdminDeleteSabbathSchoolLessonController;
use App\Http\Controllers\Api\V1\Admin\SabbathSchool\DeleteSegmentContentController as AdminDeleteSabbathSchoolSegmentContentController;
use App\Http\Controllers\Api\V1\Admin\SabbathSchool\DeleteTrimesterController as AdminDeleteSabbathSchoolTrimesterController;
use App\Http\Controllers\Api\V1\Admin\SabbathSchool\ListAdminLessonsController as AdminListSabbathSchoolLessonsController;
use App\Http\Controllers\Api\V1\Admin\SabbathSchool\ListAdminTrimestersController as AdminListSabbathSchoolTrimestersController;
use App\Http\Controllers\Api\V1\Admin\SabbathSchool\ListSegmentContentsController as AdminListSabbathSchoolSegmentContentsController;
use App\Http\Controllers\Api\V1\Admin\SabbathSchool\ReorderLessonSegmentsController;
use App\Http\Controllers\Api\V1\Admin\SabbathSchool\ReorderSegmentContentsController;
use App\Http\Controllers\Api\V1\Admin\SabbathSchool\UpdateLessonController as AdminUpdateSabbathSchoolLessonController;
use App\Http\Controllers\Api\V1\Admin\SabbathSchool\UpdateSegmentContentController as AdminUpdateSabbathSchoolSegmentContentController;
use App\Http\Controllers\Api\V1\Admin\SabbathSchool\UpdateTrimesterController as AdminUpdateSabbathSchoolTrimesterController;
use App\Http\Controllers\Api\V1\Admin\Uploads\IssuePresignedUploadController;
use App\Http\Controllers\Api\V1\Admin\Users\CreateAdminUserController;
use App\Http\Controllers\Api\V1\Admin\Users\DisableAdminUserController;
use App\Http\Controllers\Api\V1\Admin\Users\EnableAdminUserController;
use App\Http\Controllers\Api\V1\Admin\Users\ListAdminUsersController;
use App\Http\Controllers\Api\V1\Admin\Users\SendAdminPasswordResetController;
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
use App\Http\Controllers\Api\V1\Collections\ListCollectionsController;
use App\Http\Controllers\Api\V1\Collections\ShowCollectionController;
use App\Http\Controllers\Api\V1\Collections\ShowCollectionTopicController;
use App\Http\Controllers\Api\V1\Commentary\ListCommentaryChapterTextsController;
use App\Http\Controllers\Api\V1\Commentary\ListCommentaryVerseTextsController;
use App\Http\Controllers\Api\V1\Commentary\ListPublishedCommentariesController;
use App\Http\Controllers\Api\V1\Devotionals\ListDevotionalArchiveController;
use App\Http\Controllers\Api\V1\Devotionals\ListDevotionalFavoritesController;
use App\Http\Controllers\Api\V1\Devotionals\ShowDevotionalController;
use App\Http\Controllers\Api\V1\Devotionals\ToggleDevotionalFavoriteController;
use App\Http\Controllers\Api\V1\EducationalResources\ListResourceBooksController;
use App\Http\Controllers\Api\V1\EducationalResources\ListResourceCategoriesController;
use App\Http\Controllers\Api\V1\EducationalResources\ListResourcesByCategoryController;
use App\Http\Controllers\Api\V1\EducationalResources\RecordEducationalResourceDownloadController;
use App\Http\Controllers\Api\V1\EducationalResources\RecordResourceBookChapterDownloadController;
use App\Http\Controllers\Api\V1\EducationalResources\RecordResourceBookDownloadController;
use App\Http\Controllers\Api\V1\EducationalResources\ShowEducationalResourceController;
use App\Http\Controllers\Api\V1\EducationalResources\ShowResourceBookChapterController;
use App\Http\Controllers\Api\V1\EducationalResources\ShowResourceBookController;
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
use App\Http\Controllers\Api\V1\Mobile\ShowAppBootstrapController;
use App\Http\Controllers\Api\V1\Mobile\ShowMobileVersionController;
use App\Http\Controllers\Api\V1\News\ListNewsController;
use App\Http\Controllers\Api\V1\News\ShowNewsController;
use App\Http\Controllers\Api\V1\Notes\DeleteNoteController;
use App\Http\Controllers\Api\V1\Notes\ListNotesController;
use App\Http\Controllers\Api\V1\Notes\StoreNoteController;
use App\Http\Controllers\Api\V1\Notes\UpdateNoteController;
use App\Http\Controllers\Api\V1\Olympiad\FinishOlympiadAttemptController;
use App\Http\Controllers\Api\V1\Olympiad\ListOlympiadThemesController;
use App\Http\Controllers\Api\V1\Olympiad\ListUserOlympiadAttemptsController;
use App\Http\Controllers\Api\V1\Olympiad\ShowOlympiadThemeController;
use App\Http\Controllers\Api\V1\Olympiad\StartOlympiadAttemptController;
use App\Http\Controllers\Api\V1\Olympiad\SubmitOlympiadAttemptAnswersController;
use App\Http\Controllers\Api\V1\Profile\ChangeUserPasswordController;
use App\Http\Controllers\Api\V1\Profile\DeleteUserAccountController;
use App\Http\Controllers\Api\V1\Profile\RemoveUserAvatarController;
use App\Http\Controllers\Api\V1\Profile\UpdateUserProfileController;
use App\Http\Controllers\Api\V1\Profile\UploadUserAvatarController;
use App\Http\Controllers\Api\V1\QrCode\RecordQrCodeScanController;
use App\Http\Controllers\Api\V1\QrCode\ShowQrCodeController;
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
use App\Http\Controllers\Api\V1\SabbathSchool\ListSabbathSchoolTrimestersController;
use App\Http\Controllers\Api\V1\SabbathSchool\PatchSabbathSchoolHighlightController;
use App\Http\Controllers\Api\V1\SabbathSchool\ShowSabbathSchoolAnswerController;
use App\Http\Controllers\Api\V1\SabbathSchool\ShowSabbathSchoolLessonController;
use App\Http\Controllers\Api\V1\SabbathSchool\ShowSabbathSchoolTrimesterController;
use App\Http\Controllers\Api\V1\SabbathSchool\ToggleSabbathSchoolFavoriteController;
use App\Http\Controllers\Api\V1\SabbathSchool\ToggleSabbathSchoolHighlightController;
use App\Http\Controllers\Api\V1\SabbathSchool\UpsertSabbathSchoolAnswerController;
use App\Http\Controllers\Api\V1\Sync\ShowUserSyncController;
use App\Http\Controllers\Api\V1\Verses\GetDailyVerseController;
use App\Http\Controllers\Api\V1\Verses\ResolveVersesController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', RegisterController::class)->name('register');
        Route::post('login', LoginController::class)->name('login');
        Route::post('forgot-password', RequestPasswordResetController::class)->name('forgot-password');
        Route::post('reset-password', ResetPasswordController::class)->name('reset-password');

        Route::middleware(['auth:sanctum', 'throttle:per-user'])->group(function (): void {
            Route::post('logout', LogoutController::class)->name('logout');
            Route::get('me', MeController::class)->name('me');
        });
    });

    Route::prefix('bible-versions')
        ->name('bible-versions.')
        ->middleware(['api-key-or-sanctum', 'throttle:public-anon'])
        ->group(function (): void {
            Route::get('/', ListBibleVersionsController::class)
                ->middleware(['resolve-language', 'cache.headers:public;max_age=3600;etag'])
                ->name('index');
            Route::get('{version:abbreviation}/export', ExportBibleVersionController::class)->name('export');
        });

    Route::prefix('books')
        ->name('books.')
        ->middleware(['api-key-or-sanctum', 'throttle:public-anon', 'cache.headers:public;max_age=3600;etag'])
        ->group(function (): void {
            Route::get('/', ListBibleBooksController::class)
                ->middleware('resolve-language')
                ->name('index');
            Route::get('{book:abbreviation}/chapters', ListBibleBookChaptersController::class)->name('chapters');
        });

    Route::middleware(['api-key-or-sanctum', 'resolve-language', 'throttle:public-anon'])->group(function (): void {
        Route::get('verses', ResolveVersesController::class)
            ->middleware('cache.headers:public;max_age=600;etag')
            ->name('verses.index');
        Route::get('daily-verse', GetDailyVerseController::class)->name('daily-verse.show');
    });

    Route::prefix('collections')
        ->name('collections.')
        ->middleware(['api-key-or-sanctum', 'resolve-language', 'throttle:public-anon'])
        ->group(function (): void {
            Route::get('/', ListCollectionsController::class)->name('index');
            Route::get('{collection:slug}', ShowCollectionController::class)->name('show');
            Route::scopeBindings()->group(function (): void {
                Route::get('{collection:slug}/topics/{topic}', ShowCollectionTopicController::class)
                    ->name('topics.show');
            });
        });

    Route::prefix('reading-plans')
        ->name('reading-plans.')
        ->middleware(['api-key-or-sanctum', 'resolve-language', 'throttle:public-anon'])
        ->group(function (): void {
            Route::get('/', ListReadingPlansController::class)->name('index');
            Route::get('{plan:slug}', ShowReadingPlanController::class)->name('show');
            Route::post('{plan:slug}/subscriptions', StartReadingPlanSubscriptionController::class)
                ->middleware(['auth:sanctum', 'throttle:per-user'])
                ->name('subscriptions.store');
        });

    Route::prefix('olympiad')
        ->name('olympiad.')
        ->middleware(['api-key-or-sanctum', 'resolve-language', 'throttle:public-anon'])
        ->group(function (): void {
            Route::get('themes', ListOlympiadThemesController::class)->name('themes.index');
            Route::get('themes/{book}/{chapters}', ShowOlympiadThemeController::class)
                ->where('book', '[A-Za-z0-9]+')
                ->name('themes.show');
        });

    Route::prefix('olympiad/attempts')
        ->name('olympiad.attempts.')
        ->middleware(['auth:sanctum', 'resolve-language', 'throttle:per-user'])
        ->group(function (): void {
            Route::get('/', ListUserOlympiadAttemptsController::class)->name('index');
            Route::post('/', StartOlympiadAttemptController::class)->name('store');
            Route::post('{attempt}/answers', SubmitOlympiadAttemptAnswersController::class)->name('answers.store');
            Route::post('{attempt}/finish', FinishOlympiadAttemptController::class)->name('finish');
        });

    Route::middleware(['api-key-or-sanctum', 'resolve-language', 'throttle:public-anon', 'cache.headers:public;max_age=3600;etag'])
        ->group(function (): void {
            Route::get('hymnal-books', ListHymnalBooksController::class)
                ->name('hymnal-books.index');
            Route::get('hymnal-books/{book:slug}/songs', ListHymnalBookSongsController::class)
                ->name('hymnal-books.songs');
            Route::get('hymnal-songs/{song}', ShowHymnalSongController::class)
                ->name('hymnal-songs.show');
        });

    Route::prefix('commentaries')
        ->name('commentaries.')
        ->middleware(['api-key-or-sanctum', 'resolve-language', 'throttle:public-anon', 'cache.headers:public;max_age=3600;etag'])
        ->group(function (): void {
            Route::get('/', ListPublishedCommentariesController::class)->name('index');
            Route::get(
                '{commentary:slug}/{book}/{chapter}',
                ListCommentaryChapterTextsController::class,
            )
                ->where('book', '[A-Za-z0-9]+')
                ->where('chapter', '[0-9]+')
                ->name('chapter');
            Route::get(
                '{commentary:slug}/{book}/{chapter}/{verse}',
                ListCommentaryVerseTextsController::class,
            )
                ->where('book', '[A-Za-z0-9]+')
                ->where('chapter', '[0-9]+')
                ->where('verse', '[0-9]+')
                ->name('verse');
        });

    Route::middleware(['auth:sanctum', 'throttle:per-user'])
        ->prefix('hymnal-favorites')
        ->name('hymnal-favorites.')
        ->group(function (): void {
            Route::get('/', ListHymnalFavoritesController::class)->name('index');
            Route::post('toggle', ToggleHymnalFavoriteController::class)->name('toggle');
        });

    Route::prefix('devotionals')
        ->name('devotionals.')
        ->middleware(['api-key-or-sanctum', 'resolve-language', 'throttle:public-anon'])
        ->group(function (): void {
            Route::get('archive', ListDevotionalArchiveController::class)->name('archive');
            Route::get('/', ShowDevotionalController::class)->name('show');
        });

    Route::prefix('devotional-favorites')
        ->name('devotional-favorites.')
        ->middleware(['auth:sanctum', 'throttle:per-user'])
        ->group(function (): void {
            Route::get('/', ListDevotionalFavoritesController::class)->name('index');
            Route::post('toggle', ToggleDevotionalFavoriteController::class)->name('toggle');
        });

    Route::middleware(['auth:sanctum', 'throttle:per-user'])
        ->prefix('notes')
        ->name('notes.')
        ->group(function (): void {
            Route::get('/', ListNotesController::class)->name('index');
            Route::post('/', StoreNoteController::class)->name('store');
            Route::patch('{note}', UpdateNoteController::class)->name('update');
            Route::delete('{note}', DeleteNoteController::class)->name('destroy');
        });

    Route::middleware(['auth:sanctum', 'resolve-language', 'throttle:per-user'])
        ->prefix('favorite-categories')
        ->name('favorite-categories.')
        ->group(function (): void {
            Route::get('/', ListFavoriteCategoriesController::class)->name('index');
            Route::post('/', CreateFavoriteCategoryController::class)->name('store');
            Route::patch('{category}', UpdateFavoriteCategoryController::class)->name('update');
            Route::delete('{category}', DeleteFavoriteCategoryController::class)->name('destroy');
        });

    Route::middleware(['auth:sanctum', 'resolve-language', 'throttle:per-user'])
        ->prefix('favorites')
        ->name('favorites.')
        ->group(function (): void {
            Route::get('/', ListFavoritesController::class)->name('index');
            Route::post('/', CreateFavoriteController::class)->name('store');
            Route::patch('{favorite}', UpdateFavoriteController::class)->name('update');
            Route::delete('{favorite}', DeleteFavoriteController::class)->name('destroy');
        });

    Route::middleware(['api-key-or-sanctum', 'resolve-language', 'throttle:public-anon'])
        ->group(function (): void {
            Route::get('resource-categories', ListResourceCategoriesController::class)
                ->name('resource-categories.index');

            Route::get('resource-categories/{category}/resources', ListResourcesByCategoryController::class)
                ->middleware('cache.headers:public;max_age=600;etag')
                ->name('resource-categories.resources.index');

            Route::get('resources/{resource:uuid}', ShowEducationalResourceController::class)
                ->middleware('cache.headers:public;max_age=3600;etag')
                ->name('resources.show');

            Route::prefix('resource-books')
                ->name('resource-books.')
                ->group(function (): void {
                    Route::get('/', ListResourceBooksController::class)
                        ->middleware('cache.headers:public;max_age=3600;etag')
                        ->name('index');
                    Route::get('{book:slug}', ShowResourceBookController::class)
                        ->middleware('cache.headers:public;max_age=3600;etag')
                        ->name('show');
                    Route::scopeBindings()->group(function (): void {
                        Route::get('{book:slug}/chapters/{chapter}', ShowResourceBookChapterController::class)
                            ->middleware('cache.headers:public;max_age=600;etag')
                            ->name('chapters.show');
                    });
                });
        });

    Route::middleware(['api-key-or-sanctum', 'resolve-language', 'throttle:downloads'])
        ->group(function (): void {
            Route::post('resources/{resource:uuid}/downloads', RecordEducationalResourceDownloadController::class)
                ->name('resources.downloads.store');

            Route::post('resource-books/{book:slug}/downloads', RecordResourceBookDownloadController::class)
                ->name('resource-books.downloads.store');

            Route::scopeBindings()->group(function (): void {
                Route::post(
                    'resource-books/{book:slug}/chapters/{chapter}/downloads',
                    RecordResourceBookChapterDownloadController::class,
                )->name('resource-books.chapters.downloads.store');
            });
        });

    Route::middleware(['auth:sanctum', 'throttle:per-user'])
        ->prefix('profile')
        ->name('profile.')
        ->group(function (): void {
            Route::patch('/', UpdateUserProfileController::class)->name('update');
            Route::delete('/', DeleteUserAccountController::class)->name('destroy');
            Route::post('change-password', ChangeUserPasswordController::class)->name('change-password');
            Route::post('avatar', UploadUserAvatarController::class)->name('avatar.store');
            Route::delete('avatar', RemoveUserAvatarController::class)->name('avatar.destroy');
        });

    Route::prefix('admin')
        ->name('admin.')
        ->group(function (): void {
            Route::middleware(['auth:sanctum', 'super-admin'])
                ->prefix('users')
                ->name('users.')
                ->group(function (): void {
                    Route::get('/', ListAdminUsersController::class)->name('index');
                    Route::post('/', CreateAdminUserController::class)->name('store');
                    Route::patch('{user}/enable', EnableAdminUserController::class)->name('enable');
                    Route::patch('{user}/disable', DisableAdminUserController::class)->name('disable');
                    Route::post('{user}/password-reset', SendAdminPasswordResetController::class)
                        ->name('password-reset');
                });

            Route::middleware(['auth:sanctum', 'admin'])
                ->group(function (): void {
                    Route::post('resource-categories/reorder', ReorderResourceCategoriesController::class)
                        ->name('resource-categories.reorder');
                    Route::post(
                        'resource-categories/{category}/resources/reorder',
                        ReorderEducationalResourcesController::class,
                    )->name('resource-categories.resources.reorder');

                    Route::post('references/validate', ValidateReferenceController::class)
                        ->name('references.validate');

                    Route::get('imports/{job}', ShowImportJobController::class)
                        ->name('imports.show');

                    Route::post('uploads/presign', IssuePresignedUploadController::class)
                        ->name('uploads.presign');

                    Route::prefix('sabbath-school')
                        ->name('sabbath-school.')
                        ->group(function (): void {
                            Route::get('trimesters', AdminListSabbathSchoolTrimestersController::class)
                                ->name('trimesters.index');
                            Route::post('trimesters', AdminCreateSabbathSchoolTrimesterController::class)
                                ->name('trimesters.store');
                            Route::patch('trimesters/{trimester}', AdminUpdateSabbathSchoolTrimesterController::class)
                                ->name('trimesters.update');
                            Route::delete('trimesters/{trimester}', AdminDeleteSabbathSchoolTrimesterController::class)
                                ->name('trimesters.destroy');

                            Route::get('lessons', AdminListSabbathSchoolLessonsController::class)
                                ->name('lessons.index');
                            Route::post('lessons', AdminCreateSabbathSchoolLessonController::class)
                                ->name('lessons.store');
                            Route::patch('lessons/{lesson}', AdminUpdateSabbathSchoolLessonController::class)
                                ->name('lessons.update');
                            Route::delete('lessons/{lesson}', AdminDeleteSabbathSchoolLessonController::class)
                                ->name('lessons.destroy');

                            Route::post(
                                'lessons/{lesson}/segments/reorder',
                                ReorderLessonSegmentsController::class,
                            )->name('lessons.segments.reorder');

                            Route::get(
                                'segments/{segment}/contents',
                                AdminListSabbathSchoolSegmentContentsController::class,
                            )->name('segments.contents.index');
                            Route::post(
                                'segments/{segment}/contents',
                                AdminCreateSabbathSchoolSegmentContentController::class,
                            )->name('segments.contents.store');
                            Route::post(
                                'segments/{segment}/contents/reorder',
                                ReorderSegmentContentsController::class,
                            )->name('segments.contents.reorder');

                            Route::patch(
                                'segment-contents/{content}',
                                AdminUpdateSabbathSchoolSegmentContentController::class,
                            )->name('segment-contents.update');
                            Route::delete(
                                'segment-contents/{content}',
                                AdminDeleteSabbathSchoolSegmentContentController::class,
                            )->name('segment-contents.destroy');
                        });

                    Route::post(
                        'olympiad/themes/{book}/{chapters}/{language}/questions/reorder',
                        ReorderOlympiadQuestionsController::class,
                    )
                        ->where('book', '[A-Za-z0-9]+')
                        ->where('language', '[a-z]{2}')
                        ->name('olympiad.themes.questions.reorder');

                    Route::get(
                        'olympiad/attempts',
                        ListAdminOlympiadAttemptsController::class,
                    )
                        ->middleware('super-admin')
                        ->name('olympiad.attempts.index');
                });

            Route::middleware(['auth:sanctum', 'super-admin'])
                ->prefix('devotional-types')
                ->name('devotional-types.')
                ->group(function (): void {
                    Route::get('/', ListDevotionalTypesController::class)->name('index');
                    Route::post('/', CreateDevotionalTypeController::class)->name('store');
                    Route::post('reorder', ReorderDevotionalTypesController::class)->name('reorder');
                    Route::patch('{type}', UpdateDevotionalTypeController::class)->name('update');
                    Route::delete('{type}', DeleteDevotionalTypeController::class)->name('destroy');
                });

            Route::middleware(['auth:sanctum', 'super-admin'])
                ->prefix('mobile-versions')
                ->name('mobile-versions.')
                ->group(function (): void {
                    Route::get('/', ListMobileVersionsController::class)->name('index');
                    Route::post('/', CreateMobileVersionController::class)->name('store');
                    Route::patch('{version}', UpdateMobileVersionController::class)->name('update');
                    Route::delete('{version}', DeleteMobileVersionController::class)->name('destroy');
                });

            Route::middleware(['auth:sanctum', 'super-admin'])
                ->prefix('qr-codes')
                ->name('qr-codes.')
                ->group(function (): void {
                    Route::get('/', ListAdminQrCodesController::class)->name('index');
                    Route::post('/', AdminCreateQrCodeController::class)->name('store');
                    Route::patch('{qr}', AdminUpdateQrCodeController::class)->name('update');
                    Route::delete('{qr}', AdminDeleteQrCodeController::class)->name('destroy');
                });

            Route::middleware(['auth:sanctum', 'super-admin'])
                ->prefix('collections')
                ->name('collections.')
                ->group(function (): void {
                    Route::get('/', ListAdminCollectionsController::class)->name('index');
                    Route::post('/', CreateCollectionController::class)->name('store');
                    Route::patch('{collection}', UpdateCollectionController::class)->name('update');
                    Route::delete('{collection}', DeleteCollectionController::class)->name('destroy');

                    Route::scopeBindings()->group(function (): void {
                        Route::get('{collection}/topics', ListAdminCollectionTopicsController::class)
                            ->name('topics.index');
                        Route::post('{collection}/topics', CreateCollectionTopicController::class)
                            ->name('topics.store');
                        Route::patch('{collection}/topics/{topic}', UpdateCollectionTopicController::class)
                            ->name('topics.update');
                        Route::delete('{collection}/topics/{topic}', DeleteCollectionTopicController::class)
                            ->name('topics.destroy');
                    });
                });

            Route::middleware(['auth:sanctum', 'super-admin'])
                ->prefix('resource-books')
                ->name('resource-books.')
                ->group(function (): void {
                    Route::get('/', AdminListResourceBooksController::class)->name('index');
                    Route::post('/', AdminCreateResourceBookController::class)->name('store');
                    Route::post('reorder', AdminReorderResourceBooksController::class)->name('reorder');
                    Route::patch('{book}', AdminUpdateResourceBookController::class)->name('update');
                    Route::delete('{book}', AdminDeleteResourceBookController::class)->name('destroy');
                    Route::post('{book}/publish', AdminPublishResourceBookController::class)->name('publish');
                    Route::post('{book}/unpublish', AdminUnpublishResourceBookController::class)->name('unpublish');
                    Route::get('{book}/chapters', AdminListResourceBookChaptersController::class)
                        ->name('chapters.index');
                    Route::post('{book}/chapters', AdminCreateResourceBookChapterController::class)
                        ->name('chapters.store');
                    Route::post('{book}/chapters/reorder', AdminReorderResourceBookChaptersController::class)
                        ->name('chapters.reorder');
                });

            Route::middleware(['auth:sanctum', 'super-admin'])
                ->prefix('resource-book-chapters')
                ->name('resource-book-chapters.')
                ->group(function (): void {
                    Route::patch('{chapter}', AdminUpdateResourceBookChapterController::class)->name('update');
                    Route::delete('{chapter}', AdminDeleteResourceBookChapterController::class)->name('destroy');
                });

            Route::middleware(['auth:sanctum', 'super-admin'])
                ->prefix('resource-downloads')
                ->name('resource-downloads.')
                ->group(function (): void {
                    Route::get('summary', AdminShowResourceDownloadsSummaryController::class)
                        ->name('summary');
                });

            Route::middleware(['auth:sanctum', 'super-admin'])
                ->prefix('commentaries')
                ->name('commentaries.')
                ->group(function (): void {
                    Route::get('/', ListCommentariesController::class)->name('index');
                    Route::post('/', CreateCommentaryController::class)->name('store');
                    Route::patch('{commentary}', UpdateCommentaryController::class)->name('update');
                    Route::post('{commentary}/publish', PublishCommentaryController::class)->name('publish');
                    Route::post('{commentary}/unpublish', UnpublishCommentaryController::class)->name('unpublish');

                    Route::post(
                        '{commentary}/texts/reorder',
                        ReorderCommentaryTextsController::class,
                    )->name('texts.reorder');

                    Route::scopeBindings()->group(function (): void {
                        Route::get('{commentary}/texts', ListCommentaryTextsController::class)
                            ->name('texts.index');
                        Route::post('{commentary}/texts', CreateCommentaryTextController::class)
                            ->name('texts.store');
                        Route::patch('{commentary}/texts/{text}', UpdateCommentaryTextController::class)
                            ->name('texts.update');
                        Route::delete('{commentary}/texts/{text}', DeleteCommentaryTextController::class)
                            ->name('texts.destroy');
                    });
                });
        });

    Route::middleware(['auth:sanctum', 'throttle:per-user'])
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

    Route::middleware([
        'api-key-or-sanctum',
        'resolve-language',
        'throttle:public-anon',
        'cache.headers:public;max_age=300;etag',
    ])->group(function (): void {
        Route::get('news', ListNewsController::class)->name('news.index');
        Route::get('news/{news}', ShowNewsController::class)->name('news.show');
    });

    Route::middleware([
        'api-key-or-sanctum',
        'throttle:public-anon',
        'cache.headers:public;max_age=86400;etag',
    ])->group(function (): void {
        Route::get('qr-codes', ShowQrCodeController::class)->name('qr-codes.show');
    });

    Route::middleware(['api-key-or-sanctum', 'throttle:public-anon'])
        ->group(function (): void {
            Route::post('qr-codes/{qr}/scans', RecordQrCodeScanController::class)
                ->name('qr-codes.scans.store');
        });

    Route::middleware([
        'api-key-or-sanctum',
        'throttle:public-anon',
        'cache.headers:public;max_age=300',
    ])->group(function (): void {
        Route::get('mobile/version', ShowMobileVersionController::class)->name('mobile.version');
    });

    // Bootstrap endpoint — aggregates cold-start data in one round-trip.
    // Public (api-key-or-sanctum), cached at edge via Cache-Control header.
    Route::middleware(['api-key-or-sanctum', 'resolve-language', 'throttle:public-anon'])
        ->prefix('app')
        ->name('app.')
        ->group(function (): void {
            Route::get('bootstrap', ShowAppBootstrapController::class)->name('bootstrap');
        });

    // Sync delta endpoint — returns per-user changed/deleted records since a
    // given timestamp. Sanctum auth required; never reads user from request.
    Route::middleware(['auth:sanctum', 'throttle:per-user'])
        ->group(function (): void {
            Route::get('sync', ShowUserSyncController::class)->name('sync.show');
        });

    Route::prefix('sabbath-school')
        ->name('sabbath-school.')
        ->group(function (): void {
            Route::middleware(['api-key-or-sanctum', 'resolve-language', 'throttle:public-anon'])->group(function (): void {
                Route::get('trimesters', ListSabbathSchoolTrimestersController::class)
                    ->name('trimesters.index');
                Route::get('trimesters/{trimester}', ShowSabbathSchoolTrimesterController::class)
                    ->name('trimesters.show');
                Route::get('lessons', ListSabbathSchoolLessonsController::class)
                    ->name('lessons.index');
                Route::get('lessons/{lesson}', ShowSabbathSchoolLessonController::class)
                    ->name('lessons.show');
            });

            Route::middleware(['auth:sanctum', 'throttle:per-user'])->group(function (): void {
                Route::get('segment-contents/{content}/answer', ShowSabbathSchoolAnswerController::class)
                    ->name('answers.show');
                Route::post('segment-contents/{content}/answer', UpsertSabbathSchoolAnswerController::class)
                    ->name('answers.upsert');
                Route::delete('segment-contents/{content}/answer', DeleteSabbathSchoolAnswerController::class)
                    ->name('answers.destroy');

                Route::get('highlights', ListSabbathSchoolHighlightsController::class)
                    ->name('highlights.index');
                Route::post('highlights/toggle', ToggleSabbathSchoolHighlightController::class)
                    ->name('highlights.toggle');
                Route::patch('highlights/{highlight}', PatchSabbathSchoolHighlightController::class)
                    ->name('highlights.update');

                Route::get('favorites', ListSabbathSchoolFavoritesController::class)
                    ->name('favorites.index');
                Route::post('favorites/toggle', ToggleSabbathSchoolFavoriteController::class)
                    ->name('favorites.toggle');
            });
        });
});
