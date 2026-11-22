<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the {locale} URL prefix (bn|en) as the app locale.
 * The URL prefix IS the user's explicit choice, so it always wins;
 * it is also remembered in the lv_locale cookie for the root redirect.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        if (in_array($locale, ['bn', 'en'], true)) {
            app()->setLocale($locale);
            cookie()->queue(cookie('lv_locale', $locale, 60 * 24 * 365));
        }

        return $next($request);
    }
}
