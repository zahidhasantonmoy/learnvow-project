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
        // Guests hitting an auth route go to the login page in the same
        // locale as the URL they came from (B finding: route('login')
        // without {locale} throws UrlGenerationException).
        $middleware->redirectGuestsTo(function (Request $request) {
            $segment = $request->segment(1);
            $locale = in_array($segment, ['bn', 'en'], true)
                ? $segment
                : ($request->cookie('lv_locale') ?: 'bn');

            return '/'.$locale.'/login';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
