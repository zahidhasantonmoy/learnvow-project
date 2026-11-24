<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** API locale from Accept-Language (bn|en, default bn) — spec §1.3. */
class SetApiLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = strtolower(substr((string) $request->header('Accept-Language', 'bn'), 0, 2));
        app()->setLocale(in_array($locale, ['bn', 'en'], true) ? $locale : 'bn');

        return $next($request);
    }
}
