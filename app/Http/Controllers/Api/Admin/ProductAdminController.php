<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductAdminController extends Controller
{
    public function __construct(private readonly ProductService $products)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->products->listProducts(
            (int) $request->query('page', 1),
            (int) $request->query('limit', 20),
            $request->query('q') ? (string) $request->query('q') : null,
            $request->query('isActive') !== null ? filter_var($request->query('isActive'), FILTER_VALIDATE_BOOL) : null,
            $request->query('categoryId') ? (string) $request->query('categoryId') : null,
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
        $data = $this->products->getById($id, true);
        return ApiResponse::success($data['data'] ?? null);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string'],
            'slug' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'string'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'in:kg,piece'],
            'categoryId' => ['nullable', 'string'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $data = $this->products->create($payload);
        return ApiResponse::success($data['data'] ?? null, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $this->products->update($id, $request->all());
        return ApiResponse::success($data['data'] ?? null);
    }

    public function destroy(string $id): JsonResponse
    {
        $data = $this->products->delete($id);
        return ApiResponse::success($data['data'] ?? null);
    }
}
