<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetApplicationLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->session()->get('locale', $request->cookie('jooal_locale', config('app.locale')));
        if (! in_array($locale, ['id', 'en'], true)) $locale = config('app.fallback_locale', 'en');
        app()->setLocale($locale);
        return $next($request);
    }
}
