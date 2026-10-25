<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * The public entry form posts with fetch and is CSRF-exempt: a public
         * microsite is fully cacheable and carries no session to protect. It is
         * guarded by a honeypot field, a per-IP rate limit and strict
         * validation instead.
         */
        $middleware->validateCsrfTokens(except: [
            's/*/enter',
            's/*/ping',
        ]);

        /*
         * Behind Cloud's load balancer, trust the proxy so the app sees the
         * real client IP (used for rate limiting) and the https scheme.
         */
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
