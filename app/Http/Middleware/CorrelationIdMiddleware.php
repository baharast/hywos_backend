<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationIdMiddleware
{
    public const ATTRIBUTE = 'correlation_id';
    public const HEADER = 'X-Correlation-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->headers->get(self::HEADER)
            ?? $request->headers->get('X-Request-Id')
            ?? (string) Str::uuid();

        $request->attributes->set(self::ATTRIBUTE, $correlationId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }
}
