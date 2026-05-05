<?php

use App\Domain\AI\Exceptions\ClaudeUnavailableException;
use App\Domain\Auth\Exceptions\InvalidPasswordResetTokenException;
use App\Domain\Bible\Exceptions\VerseRangeTooLargeException;
use App\Domain\Commentary\Exceptions\CommentaryNotCorrectedException;
use App\Domain\Commentary\Exceptions\CommentaryTextNotCorrectedException;
use App\Domain\Commentary\Exceptions\TranslationTargetExistsException;
use App\Domain\Olympiad\Exceptions\OlympiadAnswerNotInQuestionException;
use App\Domain\Olympiad\Exceptions\OlympiadAttemptAlreadyFinishedException;
use App\Domain\Olympiad\Exceptions\OlympiadAttemptThemeMismatchException;
use App\Domain\Olympiad\Exceptions\OlympiadThemeNotFoundException;
use App\Domain\ReadingPlans\Exceptions\SubscriptionAlreadyCompletedException;
use App\Domain\ReadingPlans\Exceptions\SubscriptionNotCompletableException;
use App\Domain\Reference\Exceptions\InvalidReferenceException;
use App\Domain\SabbathSchool\Exceptions\InvalidSabbathSchoolPassageException;
use App\Domain\Verses\Exceptions\NoDailyVerseForDateException;
use App\Http\Controllers\Health\ShowLivenessController;
use App\Http\Controllers\Health\ShowReadinessController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureApiKeyOrSanctum;
use App\Http\Middleware\EnsureInternalOps;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureValidApiKey;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        apiPrefix: 'api',
        then: function (): void {
            // Liveness probe — pure PHP process check, no upstream pings.
            // Load balancers poll this every 10 s; it must never reflect
            // Redis or DB health so transient dep blips do not pull the
            // instance from rotation.
            Route::get('up', ShowLivenessController::class)->name('health');

            // Readiness probe — pings Redis + DB. VPC-only via internal-ops
            // middleware so public internet cannot probe internals.
            Route::get('ready', ShowReadinessController::class)
                ->middleware('internal-ops')
                ->name('health.ready');
        },
    )
    ->withCommands([
        __DIR__ . '/../app/Application/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*'),
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->alias([
            'api-key' => EnsureValidApiKey::class,
            'api-key-or-sanctum' => EnsureApiKeyOrSanctum::class,
            'resolve-language' => ResolveRequestLanguage::class,
            'admin' => EnsureAdmin::class,
            'super-admin' => EnsureSuperAdmin::class,
            'internal-ops' => EnsureInternalOps::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e): bool => true);

        $exceptions->render(function (ValidationException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'This action is unauthorized.',
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            return response()->json(['message' => 'Resource not found.'], 404);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return response()->json(['message' => 'Resource not found.'], 404);
        });

        $exceptions->render(function (SubscriptionNotCompletableException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'pending_days' => $e->pendingPositions,
            ], 422);
        });

        $exceptions->render(function (SubscriptionAlreadyCompletedException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        $exceptions->render(function (InvalidPasswordResetTokenException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        $exceptions->render(function (InvalidReferenceException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['reference' => [$e->reason()]],
            ], 422);
        });

        $exceptions->render(function (VerseRangeTooLargeException $e, Request $request) {
            Log::info('verse_range_too_large', [
                'reference' => $e->range->canonical(),
                'expanded' => $e->expandedSize,
                'cap' => $e->cap,
            ]);

            $message = 'The requested passage is too large.';

            return response()->json([
                'message' => $message,
                'errors' => ['reference' => [$message]],
            ], 422);
        });

        $exceptions->render(function (InvalidSabbathSchoolPassageException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['passage' => [$e->reason]],
            ], 422);
        });

        $exceptions->render(function (NoDailyVerseForDateException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 404);
        });

        $exceptions->render(function (OlympiadThemeNotFoundException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 404);
        });

        $exceptions->render(function (OlympiadAttemptAlreadyFinishedException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        $exceptions->render(function (OlympiadAttemptThemeMismatchException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        $exceptions->render(function (OlympiadAnswerNotInQuestionException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        $exceptions->render(function (CommentaryTextNotCorrectedException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        $exceptions->render(function (CommentaryNotCorrectedException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        $exceptions->render(function (TranslationTargetExistsException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'existing_commentary_id' => $e->existingCommentaryId,
            ], 409);
        });

        $exceptions->render(function (ClaudeUnavailableException $e, Request $request) {
            $payload = ['message' => $e->getMessage()];
            if ($e->aiCallId !== null) {
                $payload['ai_call_id'] = $e->aiCallId;
            }

            return response()->json(
                $payload,
                502,
                ['Retry-After' => (string) $e->retryAfterSeconds],
            );
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Server Error.',
            ], $status, $headers);
        });
    })->create();
