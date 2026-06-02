<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class LocaleMiddleware
{
    private const SUPPORTED = ['en', 'de'];

    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolveLocale($request->header('Accept-Language', '')));

        return $next($request);
    }

    private function resolveLocale(string $header): string
    {
        if ($header === '') {
            return config('app.locale', 'en');
        }

        // Parse "de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7" → first supported primary tag
        foreach (explode(',', $header) as $part) {
            $primary = strtolower(explode('-', trim(explode(';', $part)[0]))[0]);
            if (in_array($primary, self::SUPPORTED, true)) {
                return $primary;
            }
        }

        return config('app.locale', 'en');
    }
}
