<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers (audit fix SEC-04, 2026-11-24).
 *
 * Deliberately NO Content-Security-Policy: Livewire 4 + Alpine.js rely
 * on inline scripts, and a CSP strict enough to matter would break
 * them. The headers below are safe for every page in the app.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer-when-downgrade');
        $response->headers->remove('X-Powered-By');

        // X-Powered-By is also emitted by the PHP SAPI itself
        // (expose_php); remove it from the raw header list too.
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
