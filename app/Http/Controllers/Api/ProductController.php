<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products)
    {
    }

    public function customer(Request $request): JsonResponse
    {
        $data = $this->products->listProducts(
            (int) $request->query('page', 1),
            (int) $request->query('limit', 20),
            $request->query('q') ? (string) $request->query('q') : null,
            true,
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

    public function artist(Request $request): JsonResponse
    {
        $data = $this->products->listProducts(
            (int) $request->query('page', 1),
            (int) $request->query('limit', 20),
            $request->query('q') ? (string) $request->query('q') : null,
            true,
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
        $data = $this->products->getById($id, false);
        return ApiResponse::success($data['data'] ?? null);
    }
}
