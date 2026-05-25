<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\BannerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerAdminController extends Controller
{
    public function __construct(private readonly BannerService $banners)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->banners->listBanners(
            (int) $request->query('page', 1),
            (int) $request->query('limit', 20),
            $request->query('isActive') !== null ? filter_var($request->query('isActive'), FILTER_VALIDATE_BOOL) : null,
            false,
        );

        return ApiResponse::success(
            $data['data'] ?? null,
            200,
            ApiResponse::SUCCESS,
            null,
            $data['meta'] ?? null
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalizeBooleanFields($request, ['isActive']);

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['required', 'file'],
            'fileType' => ['required', 'in:image,video'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $data = $this->banners->create($payload);
        return ApiResponse::success($data['data'] ?? null, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->normalizeBooleanFields($request, ['isActive']);

        $payload = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'file' => ['sometimes', 'file'],
            'fileType' => ['sometimes', 'in:image,video'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $data = $this->banners->update($id, $payload);
        return ApiResponse::success($data['data'] ?? null);
    }

    public function destroy(string $id): JsonResponse
    {
        $data = $this->banners->delete($id);
        return ApiResponse::success($data['data'] ?? null);
    }

    private function normalizeBooleanFields(Request $request, array $keys): void
    {
        foreach ($keys as $key) {
            if (!$request->has($key)) {
                continue;
            }

            $raw = $request->input($key);
            if (is_bool($raw)) {
                continue;
            }

            $value = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value !== null) {
                $request->merge([$key => $value]);
            }
        }
    }
}
