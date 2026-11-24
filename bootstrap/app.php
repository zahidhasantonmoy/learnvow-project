<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // Phase 4 — Sanctum API (spec §1.1)
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SEC-04: security headers on every response (no CSP — it would
        // break Livewire/Alpine inline scripts; see the middleware).
        $middleware->append(SecurityHeaders::class);

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

        // SEC-06: API 404s must not leak internal model class names
        // ("No query results for model [App\Models\Book] 8"). Laravel
        // converts ModelNotFoundException to NotFoundHttpException before
        // render callbacks run, so catch the latter and return a generic
        // "Not found." in the usual envelope instead.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $locale = strtolower(substr((string) $request->header('Accept-Language', 'bn'), 0, 2));
            $locale = in_array($locale, ['bn', 'en'], true) ? $locale : 'bn';
            app()->setLocale($locale);

            return response()->json([
                'message' => __('api.not_found'),
                'meta' => ['locale' => $locale],
            ], 404);
        });

        // QA N3: the throttle 429 was a bare {"message": ...} with no
        // meta:{locale}, unlike every other API error. Envelope it for
        // /api/* requests, keeping the throttle headers (Retry-After,
        // X-RateLimit-*). The throttle middleware runs before
        // SetApiLocale, so derive the locale from Accept-Language the
        // same way that middleware does (bn|en, default bn).
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $locale = strtolower(substr((string) $request->header('Accept-Language', 'bn'), 0, 2));
            $locale = in_array($locale, ['bn', 'en'], true) ? $locale : 'bn';

            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Too Many Attempts.',
                'meta' => ['locale' => $locale],
            ], 429, $e->getHeaders());
        });
    })->create();
