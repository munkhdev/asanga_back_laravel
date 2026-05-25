<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\CategoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryAdminController extends Controller
{
    public function __construct(private readonly CategoryService $categories)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->categories->listCategories(
            (int) $request->query('page', 1),
            (int) $request->query('limit', 20),
            $request->query('q') ? (string) $request->query('q') : null,
            $request->query('isActive') !== null ? filter_var($request->query('isActive'), FILTER_VALIDATE_BOOL) : null,
        );

        return ApiResponse::success(
            $data['data'] ?? null,
            200,
            ApiResponse::SUCCESS,
            null,
            $data['meta'] ?? null
        );
    }

    public function show(string $id): JsonResponse
    {
        $data = $this->categories->getById($id);
        return ApiResponse::success($data['data'] ?? null);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string'],
            'slug' => ['nullable', 'string'],
            'icon' => ['nullable', 'string'],
            'image' => ['nullable', 'string'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $data = $this->categories->create($payload);
        return ApiResponse::success($data['data'] ?? null, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $this->categories->update($id, $request->all());
        return ApiResponse::success($data['data'] ?? null);
    }

    public function destroy(string $id): JsonResponse
    {
        $data = $this->categories->delete($id);
        return ApiResponse::success($data['data'] ?? null);
    }
}
