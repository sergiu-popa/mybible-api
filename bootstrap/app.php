<?php

use App\Domain\Auth\Exceptions\InvalidPasswordResetTokenException;
use App\Domain\Olympiad\Exceptions\OlympiadThemeNotFoundException;
use App\Domain\ReadingPlans\Exceptions\SubscriptionAlreadyCompletedException;
use App\Domain\ReadingPlans\Exceptions\SubscriptionNotCompletableException;
use App\Domain\Reference\Exceptions\InvalidReferenceException;
use App\Domain\SabbathSchool\Exceptions\InvalidSabbathSchoolPassageException;
use App\Domain\Verses\Exceptions\NoDailyVerseForDateException;
use App\Http\Middleware\EnsureApiKeyOrSanctum;
use App\Http\Middleware\EnsureValidApiKey;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withCommands([
        __DIR__ . '/../app/Application/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api-key' => EnsureValidApiKey::class,
            'api-key-or-sanctum' => EnsureApiKeyOrSanctum::class,
            'resolve-language' => ResolveRequestLanguage::class,
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

        $exceptions->render(function (Throwable $e, Request $request) {
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Server Error.',
            ], $status);
        });
    })->create();
