<?php
// hi
declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public const SUCCESS = 'Амжилттай';
    public const USER_CREATED = 'Хэрэглэгч амжилттай үүслээ';
    public const LOGIN_SUCCESS = 'Нэвтрэлт амжилттай';

    public static function success(
        mixed $data = null,
        int $status = 200,
        string $message = self::SUCCESS,
        ?string $code = null,
        mixed $meta = null
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'status' => $status,
            'message' => $message,
            'code' => $code,
            'data' => $data,
            'meta' => $meta,
        ], $status);
    }

    public static function error(
        string $message,
        int $status = 500,
        string $code = 'SERVER_ERROR',
        mixed $errors = null
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'status' => $status,
            'message' => $message,
            'code' => $code,
            'errors' => $errors,
        ], $status);
    }
}
