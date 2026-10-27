<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
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
            's/*/send-code',
            's/*/ping',
        ]);

        /*
         * Behind Cloud's load balancer, trust the proxy for the real client IP
         * (rate limiting), port and scheme — but NOT X-Forwarded-Host, so a
         * spoofed host header cannot poison generated URLs or the page cache.
         */
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
