<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->orders->listMyOrders(
            (string) $request->user()->id,
            (int) $request->query('page', 1),
            (int) $request->query('limit', 20)
        );

        return ApiResponse::success(
            $data['data'] ?? null,
            200,
            ApiResponse::SUCCESS,
            null,
            $data['meta'] ?? null
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $data = $this->orders->getOrderByIdForUser(
            $id,
            (string) $request->user()->id,
            (string) ($request->user()->role ?? 'user')
        );

        return ApiResponse::success($data['data'] ?? null);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'deliveryMethod' => ['nullable', 'in:delivery,pickup'],
            'paymentMethod' => ['nullable', 'in:qpay,bank_app'],
            'shippingAddress' => ['nullable', 'array'],
        ]);

        $created = $this->orders->createOrder(
            userId: (string) $request->user()->id,
            requester: $request->user(),
            items: $payload['items'],
            deliveryMethod: (string) ($payload['deliveryMethod'] ?? 'delivery'),
            paymentMethod: (string) ($payload['paymentMethod'] ?? 'qpay'),
            shippingAddress: $payload['shippingAddress'] ?? null
        );

        return ApiResponse::success($created['data'] ?? null, 201);
    }

    public function createQpayInvoice(Request $request, string $id): JsonResponse
    {
        $result = $this->orders->createQpayInvoiceForOrder(
            id: $id,
            userId: (string) $request->user()->id,
            requesterRole: (string) ($request->user()->role ?? 'user')
        );

        return ApiResponse::success($result['data'] ?? null);
    }

    public function checkQpayStatus(Request $request, string $id): JsonResponse
    {
        $result = $this->orders->checkQpayPaymentForOrder(
            id: $id,
            userId: (string) $request->user()->id,
            requesterRole: (string) ($request->user()->role ?? 'user')
        );

        return ApiResponse::success($result['data'] ?? null);
    }
}
