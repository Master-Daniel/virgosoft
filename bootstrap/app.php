<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Apply CORS to API routes
        $middleware->api(prepend: [
            \App\Http\Middleware\HandleCors::class,
        ]);
        // Apply CORS to web routes (needed for broadcasting/auth)
        $middleware->web(prepend: [
            \App\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle authentication exceptions for API/broadcasting routes
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            // For API or broadcasting routes, return JSON instead of redirecting
            if ($request->is('api/*') || $request->is('broadcasting/*')) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
        });
    })->create();
