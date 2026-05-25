<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->orders->listAdminOrders(
            filters: [
                'userId' => $request->query('userId'),
                'status' => $request->query('status'),
                'paymentStatus' => $request->query('paymentStatus'),
                'type' => $request->query('type'),
            ],
            page: (int) $request->query('page', 1),
            limit: (int) $request->query('limit', 50),
        );

        return ApiResponse::success(
            $result['data'] ?? null,
            200,
            ApiResponse::SUCCESS,
            null,
            $result['meta'] ?? null,
        );
    }

    public function show(string $id): JsonResponse
    {
        $result = $this->orders->getAdminOrderById($id);
        return ApiResponse::success($result['data'] ?? null);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['nullable', 'string'],
            'paymentStatus' => ['nullable', 'string'],
            'shippingStatus' => ['nullable', 'string'],
        ]);

        $result = $this->orders->updateAdminOrder($id, $payload);
        return ApiResponse::success($result['data'] ?? null);
    }
}
