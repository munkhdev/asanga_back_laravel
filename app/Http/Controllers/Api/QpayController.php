<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Support\ApiResponse;
use App\Support\QpayClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QpayController extends Controller
{
    public function __construct(
        private readonly QpayClient $qpay,
        private readonly OrderService $orders
    )
    {
    }

    public function token(): JsonResponse
    {
        return ApiResponse::success($this->qpay->issueToken());
    }

    public function callback(Request $request, string $sender_invoice_no): JsonResponse
    {
        $order = $this->orders->handleQpayCallbackBySenderInvoiceNo($sender_invoice_no);

        return ApiResponse::success([
            'ok' => true,
            'senderInvoiceNo' => $sender_invoice_no,
            'paid' => (bool) ($order && (string) $order->payment_status === 'paid'),
        ]);
    }
}
