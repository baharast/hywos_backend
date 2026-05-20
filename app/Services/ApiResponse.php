<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ApiResponse
{
    protected static function correlationId(): string
    {
        $cid = request()->header('X-Correlation-Id') ?? request()->header('X-Request-Id');
        return $cid ?: (string) Str::uuid();
    }

    public static function success(mixed $data = null, string $message = 'OK', int $status = 200, array $meta = null): JsonResponse
    {
        $payload = [
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'correlation_id' => self::correlationId(),
        ];

        return response()->json(array_filter($payload, fn($v) => $v !== null), $status);
    }

    public static function created(mixed $data = null, string $message = 'Created', array $meta = null): JsonResponse
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

    public static function error(string $message = 'Error', string $code = 'ERROR', int $status = 400, array $details = null): JsonResponse
    {
        $payload = [
            'message' => $message,
            'code' => $code,
            'details' => $details,
            'correlation_id' => self::correlationId(),
        ];

        return response()->json(array_filter($payload, fn($v) => $v !== null), $status);
    }

    public static function validation(array $errors, string $message = 'Validation Failed'): JsonResponse
    {
        return self::error($message, 'VALIDATION_ERROR', 422, ['errors' => $errors]);
    }

    public static function notFound(string $message = 'Not Found', array $details = null): JsonResponse
    {
        return self::error($message, 'NOT_FOUND', 404, $details);
    }

    public static function conflict(string $message = 'Conflict', array $details = null): JsonResponse
    {
        return self::error($message, 'CONFLICT', 409, $details);
    }

    public static function paginated($paginator, string $message = 'OK'): JsonResponse
    {
        $data = $paginator->items();
        $meta = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];

        return self::success($data, $message, 200, $meta);
    }
}
