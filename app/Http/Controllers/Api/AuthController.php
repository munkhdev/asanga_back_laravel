<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'firstName' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'lastName' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['nullable', 'in:user,artist,admin,sub-admin'],
        ]);

        $result = $this->auth->requestRegisterOtp($payload);
        return ApiResponse::success($result, 200, 'SMS баталгаажуулах заавар амжилттай үүслээ');
    }

    public function registerWithEmail(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:191'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $result = $this->auth->registerWithEmail($payload);
        return ApiResponse::success($result['data'] ?? null, 201, ApiResponse::USER_CREATED);
    }

    public function verifyRegisterOtp(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'registrationId' => ['required', 'string'],
            'otp' => ['nullable', 'string', 'size:4'],
        ]);

        $result = $this->auth->verifyRegisterOtp($payload);
        return ApiResponse::success($result, 201, ApiResponse::USER_CREATED);
    }

    public function verifyMnCallback(Request $request): JsonResponse
    {
        $payload = [
            'sessionId' => (string) (
                $request->query('sessionId')
                ?? $request->query('session_id')
                ?? $request->query('sessionid')
                ?? $request->input('sessionId')
                ?? $request->input('session_id')
                ?? $request->input('sessionid')
                ?? ''
            ),
        ];

        $result = $this->auth->handleVerifyMnCallback($payload);
        return ApiResponse::success($result);
    }

    public function registerStatus(string $registrationId): JsonResponse
    {
        $result = $this->auth->registerStatus($registrationId);
        return ApiResponse::success($result);
    }

    public function login(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
            'password' => ['required', 'string'],
        ]);

        $result = $this->auth->login($payload);
        return ApiResponse::success($result['data'] ?? null, 200, ApiResponse::LOGIN_SUCCESS);
    }

    public function google(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'idToken' => ['required', 'string'],
        ]);

        $result = $this->auth->loginWithGoogle((string) $payload['idToken']);
        return ApiResponse::success($result['data'] ?? null, 200, ApiResponse::LOGIN_SUCCESS);
    }

    public function facebook(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'accessToken' => ['required', 'string'],
        ]);

        $result = $this->auth->loginWithFacebook((string) $payload['accessToken']);
        return ApiResponse::success($result['data'] ?? null, 200, ApiResponse::LOGIN_SUCCESS);
    }

    public function me(Request $request): JsonResponse
    {
        $result = $this->auth->me($request->user());
        return ApiResponse::success($result['data'] ?? null);
    }

    public function logout(Request $request): JsonResponse
    {
        $result = $this->auth->logout($request->user());
        return ApiResponse::success($result['data'] ?? null);
    }
}
