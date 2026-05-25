<?php

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            $errors = $e->errors();
            $firstError = null;
            foreach ($errors as $messages) {
                if (is_array($messages) && isset($messages[0]) && is_string($messages[0]) && $messages[0] !== '') {
                    $firstError = $messages[0];
                    break;
                }
            }

            return ApiResponse::error(
                message: $firstError ?: 'Оролтын өгөгдөл буруу байна',
                status: 422,
                code: 'VALIDATION_ERROR',
                errors: $errors,
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                message: 'Нэвтрэх эрхгүй байна',
                status: 401,
                code: 'UNAUTHORIZED',
            );
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                message: 'Хандах эрхгүй байна',
                status: 403,
                code: 'FORBIDDEN',
            );
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                message: 'Олдсонгүй',
                status: 404,
                code: 'NOT_FOUND',
            );
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            if (app()->hasDebugModeEnabled()) {
                return ApiResponse::error(
                    message: $e->getMessage() !== '' ? $e->getMessage() : 'Серверийн алдаа',
                    status: 500,
                    code: 'SERVER_ERROR',
                );
            }

            return ApiResponse::error(
                message: 'Серверийн алдаа',
                status: 500,
                code: 'SERVER_ERROR',
            );
        });
    })->create();
