<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ApiResponse
{
    protected static function correlationId(): string
    {
        /** @var \Illuminate\Http\Request $request */
        $request = request();
        $fromAttr = $request->attributes->get(\App\Http\Middleware\CorrelationIdMiddleware::ATTRIBUTE);
        if ($fromAttr) {
            return (string) $fromAttr;
        }
        $cid = $request->header('X-Correlation-Id') ?? $request->header('X-Request-Id');
        return is_string($cid) && $cid !== '' ? $cid : (string) Str::uuid();
    }

    public static function success(mixed $data = null, string $message = 'OK', int $status = 200, ?array $meta = null): JsonResponse
    {
        $payload = [
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'correlation_id' => self::correlationId(),
        ];

        return response()->json(array_filter($payload, fn ($v) => $v !== null), $status);
    }

    public static function created(mixed $data = null, string $message = 'Created', ?array $meta = null): JsonResponse
    {
        return self::success($data, $message, 201, $meta);
    }

    public static function noContent(string $message = 'No Content'): JsonResponse
    {
        $payload = [
            'message' => $message,
            'correlation_id' => self::correlationId(),
        ];

        return response()->json($payload, 204);
    }

    public static function error(string $message = 'Error', string $code = 'ERROR', int $status = 400, ?array $details = null): JsonResponse
    {
        $payload = [
            'message' => $message,
            'code' => $code,
            'details' => $details,
            'correlation_id' => self::correlationId(),
        ];

        return response()->json(array_filter($payload, fn ($v) => $v !== null), $status);
    }

    public static function validation(array $errors, string $message = 'Validation Failed'): JsonResponse
    {
        return self::error($message, 'VALIDATION_ERROR', 422, ['errors' => $errors]);
    }

    public static function notFound(string $message = 'Not Found', ?array $details = null): JsonResponse
    {
        return self::error($message, 'NOT_FOUND', 404, $details);
    }

    public static function conflict(string $message = 'Conflict', ?array $details = null): JsonResponse
    {
        return self::error($message, 'CONFLICT', 409, $details);
    }

    /**
     * Legacy paginated response. Prefer `list()` for the standardized shape.
     */
    public static function paginated($paginator, string $message = 'OK'): JsonResponse
    {
        return self::list($paginator->items(), $paginator, null, null, $message);
    }

    /**
     * Standardized list response for index endpoints.
     *
     * Shape:
     * {
     *   "message": "...",
     *   "data": [...],
     *   "summary": {...} | null,
     *   "meta": {
     *     "current_page", "per_page", "total", "last_page",
     *     "first_visible_row", "last_visible_row"
     *   },
     *   "last_updated_at": "ISO 8601" | null,
     *   "correlation_id": "..."
     * }
     *
     * @param  mixed                                   $data
     * @param  LengthAwarePaginator|array|null         $paginator    when null, meta is omitted
     * @param  array|null                              $summary      overview-card counts
     * @param  string|\DateTimeInterface|null          $lastUpdatedAt
     */
    public static function list(
        mixed $data,
        $paginator = null,
        ?array $summary = null,
        $lastUpdatedAt = null,
        string $message = 'OK',
        int $status = 200
    ): JsonResponse {
        $meta = null;
        if ($paginator instanceof LengthAwarePaginator) {
            $perPage = $paginator->perPage();
            $current = $paginator->currentPage();
            $total = $paginator->total();
            $first = $total > 0 ? (($current - 1) * $perPage) + 1 : 0;
            $last = min($current * $perPage, $total);

            $meta = [
                'current_page' => $current,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $paginator->lastPage(),
                'first_visible_row' => $first,
                'last_visible_row' => $last,
            ];
        } elseif (is_array($paginator)) {
            $meta = $paginator;
        }

        $payload = [
            'message' => $message,
            'data' => $data,
            'summary' => $summary,
            'meta' => $meta,
            'last_updated_at' => self::formatTimestamp($lastUpdatedAt),
            'correlation_id' => self::correlationId(),
        ];

        return response()->json(array_filter($payload, fn ($v) => $v !== null), $status);
    }

    protected static function formatTimestamp($value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }
        return (string) $value;
    }
}
