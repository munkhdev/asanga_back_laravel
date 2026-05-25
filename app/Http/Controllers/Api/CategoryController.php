<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CategoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
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
}
