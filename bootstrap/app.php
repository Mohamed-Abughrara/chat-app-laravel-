<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Src\Domain\Shared\Conflict;
use Src\Domain\Shared\DomainError;
use Src\Domain\Shared\InvalidCredentials;
use Src\Domain\Shared\NotAuthorized;
use Src\Domain\Shared\NotFound;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Echo authorises private-user.{id} against /api/broadcasting/auth using the device's bearer token.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']])
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Business-rule violations become a stable JSON error shape: { error: { code, message, retryable } }.
        $exceptions->render(function (DomainError $e, Request $request) {
            $status = match (true) {
                $e instanceof NotFound => 404,
                $e instanceof NotAuthorized => 403,
                $e instanceof InvalidCredentials => 401,
                $e instanceof Conflict => 409,
                default => 422,
            };

            return response()->json([
                'error' => ['code' => $e->errorCode(), 'message' => $e->getMessage(), 'retryable' => $e->retryable()],
            ], $status);
        });
    })->create();
