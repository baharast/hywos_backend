<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiResponse;

class ApiController extends Controller
{
    protected function success($data = null, string $message = 'OK', array $meta = null)
    {
        return ApiResponse::success($data, $message, 200, $meta);
    }

    protected function created($data = null, string $message = 'Created', array $meta = null)
    {
        return ApiResponse::created($data, $message, $meta);
    }

    protected function error(string $message = 'Error', string $code = 'ERROR', int $status = 400, array $details = null)
    {
        return ApiResponse::error($message, $code, $status, $details);
    }

    protected function validation(array $errors, string $message = 'Validation Failed')
    {
        return ApiResponse::validation($errors, $message);
    }
}
