<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Support\QpayClient;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class OrderService
{
    public function __construct(private readonly QpayClient $qpay)
    {
    }

    public function createOrder(
        string $userId,
        mixed $requester,
        array $items,
        string $deliveryMethod,
        string $paymentMethod,
        ?array $shippingAddress
    ): array {
        $method = $deliveryMethod === 'pickup' ? 'pickup' : 'delivery';
        $payMethod = $paymentMethod === 'bank_app' ? 'bank_app' : 'qpay';

        if ($method === 'delivery' && empty($shippingAddress)) {
            throw new RuntimeException('shippingAddress is required for delivery');
        }

        $productIds = collect($items)
            ->pluck('productId')
            ->filter()
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy(fn (Product $p) => (string) $p->id);

        $normalizedItems = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $productId = (string) Arr::get($item, 'productId', '');
            /** @var Product|null $product */
            $product = $products->get($productId);

            if (!$product) {
                throw new RuntimeException('PRODUCT_NOT_FOUND: ' . $productId);
            }

            if (!$product->is_active) {
                throw new RuntimeException('PRODUCT_INACTIVE: ' . $productId);
            }

            $quantity = (float) Arr::get($item, 'quantity', 0);
            if ($quantity <= 0) {
                throw new RuntimeException('INVALID_QUANTITY: ' . $productId);
            }

            if ((float) $product->stock < $quantity) {
                throw new RuntimeException('OUT_OF_STOCK: ' . $productId);
            }

            $price = (float) $product->price;
            $subtotal += $price * $quantity;

            $normalizedItems[] = [
                'productId' => (string) $product->id,
                'name' => (string) $product->name,
                'price' => $price,
                'quantity' => $quantity,
            ];
        }

        $shippingFee = $this->calculateShippingFee($method);
        $total = $subtotal + $shippingFee;

        $order = Order::query()->create([
            'user_id' => $userId,
            'type' => 'product',
            'delivery_method' => $method,
            'payment_method' => $payMethod,
            'items' => $normalizedItems,
            'subtotal' => $subtotal,
            'shipping_fee' => $shippingFee,
            'total' => $total,
            'status' => 'pending',
            'payment_status' => 'pending',
            'shipping_address' => $method === 'delivery' ? $shippingAddress : null,
        ]);

        return ['data' => $this->serializeOrder($order)];
    }

    public function listMyOrders(string $userId, int $page = 1, int $limit = 20): array
    {
        /** @var LengthAwarePaginator $paginator */
        $paginator = Order::query()
            ->where('user_id', $userId)
            ->latest('created_at')
            ->paginate($limit, ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())
                ->map(fn (Order $order) => $this->serializeOrder($order))
                ->values()
                ->all(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pages' => $paginator->lastPage(),
            ],
        ];
    }

    public function listAdminOrders(array $filters, int $page = 1, int $limit = 50): array
    {
        $query = Order::query();

        $userId = trim((string) ($filters['userId'] ?? ''));
        if ($userId !== '') {
            $query->where('user_id', $userId);
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $paymentStatus = trim((string) ($filters['paymentStatus'] ?? ''));
        if ($paymentStatus !== '') {
            $query->where('payment_status', $paymentStatus);
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $query->where('type', $type);
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query
            ->latest('created_at')
            ->paginate($limit, ['*'], 'page', $page);

        $userIds = collect($paginator->items())
            ->pluck('user_id')
            ->filter()
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();

        $usersById = User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'first_name', 'last_name', 'phone', 'email'])
            ->keyBy(fn (User $u) => (string) $u->id);

        return [
            'data' => collect($paginator->items())
                ->map(function (Order $order) use ($usersById): array {
                    $user = $usersById->get((string) $order->user_id);
                    return $this->serializeOrder($order, $user);
                })
                ->values()
                ->all(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pages' => $paginator->lastPage(),
            ],
        ];
    }

    public function getAdminOrderById(string $id): array
    {
        $order = Order::query()->findOrFail($id);
        $user = User::query()
            ->where('id', (string) $order->user_id)
            ->first(['id', 'first_name', 'last_name', 'phone', 'email']);

        return ['data' => $this->serializeOrder($order, $user)];
    }

    public function updateAdminOrder(string $id, array $payload): array
    {
        $order = Order::query()->findOrFail($id);

        if (array_key_exists('status', $payload) && $payload['status'] !== null) {
            $order->status = (string) $payload['status'];
        }

        if (array_key_exists('paymentStatus', $payload) && $payload['paymentStatus'] !== null) {
            $order->payment_status = (string) $payload['paymentStatus'];
        }

        if (array_key_exists('shippingStatus', $payload)) {
            $address = is_array($order->shipping_address) ? $order->shipping_address : [];
            $address['shippingStatus'] = $payload['shippingStatus'];
            $order->shipping_address = $address;
        }

        $order->save();

        $user = User::query()
            ->where('id', (string) $order->user_id)
            ->first(['id', 'first_name', 'last_name', 'phone', 'email']);

        return ['data' => $this->serializeOrder($order, $user)];
    }

    public function getOrderByIdForUser(string $id, string $userId, string $requesterRole): array
    {
        $order = Order::query()->findOrFail($id);
        $isOwner = (string) $order->user_id === $userId;
        $isAdmin = $requesterRole === 'admin';

        if (!$isOwner && !$isAdmin) {
            throw new RuntimeException('Forbidden');
        }

        return ['data' => $this->serializeOrder($order)];
    }

    public function createQpayInvoiceForOrder(string $id, string $userId, string $requesterRole): array
    {
        $order = Order::query()->findOrFail($id);
        $this->ensureOwnerOrAdmin($order, $userId, $requesterRole);

        if ($order->payment_status === 'paid') {
            return ['data' => ['order' => $this->serializeOrder($order), 'invoice' => null]];
        }

        $senderInvoiceNo = $this->buildSenderInvoiceNo((string) $order->id);
        $callbackBase = rtrim((string) config('qpay.callback_base_url', ''), '/');

        if ($callbackBase === '') {
            throw new RuntimeException('QPAY_CALLBACK_BASE_URL is missing');
        }

        $invoiceData = $this->qpay->createInvoice([
            'amount' => (float) $order->total,
            'description' => (string) config('qpay.invoice_description', 'Төлбөр'),
            'callbackUrl' => $callbackBase . '/' . urlencode($senderInvoiceNo),
            'senderInvoiceNo' => $senderInvoiceNo,
        ]);

        $invoiceId = Arr::get($invoiceData, 'invoice_id')
            ?? Arr::get($invoiceData, 'invoiceId')
            ?? Arr::get($invoiceData, 'id');

        if (empty($invoiceId)) {
            throw new RuntimeException('QPay invoice_id not found');
        }

        $urls = $this->normalizeQpayUrls($invoiceData);

        $order->qpay_invoice_id = (string) $invoiceId;
        $order->qpay_sender_invoice_no = $senderInvoiceNo;
        $order->qpay_qr_text = Arr::get($invoiceData, 'qr_text') ?? Arr::get($invoiceData, 'qrText');
        $order->qpay_qr_image_base64 = Arr::get($invoiceData, 'qr_image') ?? Arr::get($invoiceData, 'qrImage');
        $order->qpay_invoice_created_at = now();
        $order->qpay_urls = $urls;
        $order->save();

        return [
            'data' => [
                'order' => $this->serializeOrder($order),
                'invoice' => [
                    'invoiceId' => $order->qpay_invoice_id,
                    'senderInvoiceNo' => $order->qpay_sender_invoice_no,
                    'qrText' => $order->qpay_qr_text,
                    'qrImageBase64' => $order->qpay_qr_image_base64,
                    'urls' => $urls,
                ],
            ],
        ];
    }

    public function checkQpayPaymentForOrder(string $id, string $userId, string $requesterRole): array
    {
        $order = Order::query()->findOrFail($id);
        $this->ensureOwnerOrAdmin($order, $userId, $requesterRole);

        if (empty($order->qpay_invoice_id)) {
            throw new RuntimeException('QPay invoice is missing');
        }

        $status = $this->qpay->checkInvoicePayment((string) $order->qpay_invoice_id);
        $paymentStatus = strtoupper((string) ($status['paymentStatus'] ?? ''));

        if ($paymentStatus === 'PAID') {
            $order->payment_status = 'paid';
            $order->status = 'paid';
            $order->payment_id = $order->payment_id ?: 'qpay:' . $order->qpay_invoice_id;
            $order->save();
        }

        return [
            'data' => [
                'paid' => $paymentStatus === 'PAID',
                'paymentStatus' => $paymentStatus,
                'order' => $this->serializeOrder($order),
                'raw' => $status['raw'] ?? null,
            ],
        ];
    }

    public function handleQpayCallbackBySenderInvoiceNo(string $senderInvoiceNo): ?Order
    {
        $senderInvoiceNo = trim($senderInvoiceNo);
        if ($senderInvoiceNo === '') {
            return null;
        }

        /** @var Order|null $order */
        $order = Order::query()
            ->where('qpay_sender_invoice_no', $senderInvoiceNo)
            ->first();

        if (!$order) {
            return null;
        }

        if (empty($order->qpay_invoice_id)) {
            return $order;
        }

        $status = $this->qpay->checkInvoicePayment((string) $order->qpay_invoice_id);
        $paymentStatus = strtoupper((string) ($status['paymentStatus'] ?? ''));

        if ($paymentStatus === 'PAID' && $order->payment_status !== 'paid') {
            $order->payment_status = 'paid';
            $order->status = 'paid';
            $order->payment_id = $order->payment_id ?: 'qpay:' . $order->qpay_invoice_id;
            $order->save();
        }

        return $order;
    }

    private function calculateShippingFee(string $deliveryMethod): float
    {
        if ($deliveryMethod === 'pickup') {
            return 0;
        }

        $org = Organization::query()
            ->where('singleton', 'default')
            ->first();

        return max(0, (float) ($org?->shipping_price ?? 0));
    }

    private function ensureOwnerOrAdmin(Order $order, string $userId, string $requesterRole): void
    {
        $isOwner = (string) $order->user_id === $userId;
        $isAdmin = $requesterRole === 'admin';

        if (!$isOwner && !$isAdmin) {
            throw new RuntimeException('Forbidden');
        }
    }

    private function buildSenderInvoiceNo(string $orderId): string
    {
        $tail = Str::of($orderId)->substr(-8)->value();
        $ts = base_convert((string) now()->timestamp, 10, 36);
        $rand = base_convert((string) random_int(1000, 999999), 10, 36);

        return Str::of("PLN-{$ts}-{$tail}-{$rand}")
            ->substr(0, 45)
            ->value();
    }

    private function normalizeQpayUrls(array $data): array
    {
        $raw = Arr::get($data, 'urls')
            ?? Arr::get($data, 'links')
            ?? Arr::get($data, 'payment_urls')
            ?? Arr::get($data, 'paymentUrls')
            ?? [];

        if (!is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->map(function ($item): array {
                $item = is_array($item) ? $item : [];
                return [
                    'name' => trim((string) ($item['name'] ?? $item['title'] ?? $item['bank_name'] ?? '')) ?: null,
                    'description' => trim((string) ($item['description'] ?? $item['subTitle'] ?? '')) ?: null,
                    'link' => trim((string) ($item['link'] ?? $item['url'] ?? $item['deeplink'] ?? $item['deep_link'] ?? '')) ?: null,
                    'logo' => trim((string) ($item['logo'] ?? $item['logo_url'] ?? $item['icon'] ?? '')) ?: null,
                ];
            })
            ->filter(fn (array $v): bool => !empty($v['link']))
            ->values()
            ->all();
    }

    private function serializeOrder(Order $order, ?User $user = null): array
    {
        $arr = $order->toArray();

        $shippingAddress = is_array($arr['shipping_address'] ?? null)
            ? $arr['shipping_address']
            : null;

        $shippingStatus = null;
        if (is_array($shippingAddress) && array_key_exists('shippingStatus', $shippingAddress)) {
            $shippingStatus = $shippingAddress['shippingStatus'];
            unset($shippingAddress['shippingStatus']);
        }

        return [
            'id' => (string) $order->id,
            '_id' => (string) $order->id,
            'userId' => $user
                ? [
                    'id' => (string) $user->id,
                    '_id' => (string) $user->id,
                    'first_name' => (string) ($user->first_name ?? ''),
                    'last_name' => (string) ($user->last_name ?? ''),
                    'phone' => (string) ($user->phone ?? ''),
                    'email' => (string) ($user->email ?? ''),
                ]
                : (string) $order->user_id,
            'type' => (string) ($arr['type'] ?? 'product'),
            'status' => (string) ($arr['status'] ?? 'pending'),
            'paymentStatus' => (string) ($arr['payment_status'] ?? 'pending'),
            'payment_status' => (string) ($arr['payment_status'] ?? 'pending'),
            'shippingStatus' => $shippingStatus,
            'deliveryMethod' => (string) ($arr['delivery_method'] ?? 'delivery'),
            'delivery_method' => (string) ($arr['delivery_method'] ?? 'delivery'),
            'paymentMethod' => (string) ($arr['payment_method'] ?? 'qpay'),
            'payment_method' => (string) ($arr['payment_method'] ?? 'qpay'),
            'items' => is_array($arr['items'] ?? null) ? $arr['items'] : [],
            'subtotal' => (float) ($arr['subtotal'] ?? 0),
            'shippingFee' => (float) ($arr['shipping_fee'] ?? 0),
            'shipping_fee' => (float) ($arr['shipping_fee'] ?? 0),
            'total' => (float) ($arr['total'] ?? 0),
            'shippingAddress' => $shippingAddress,
            'shipping_address' => $shippingAddress,
            'qpayInvoiceId' => $arr['qpay_invoice_id'] ?? null,
            'qpay_invoice_id' => $arr['qpay_invoice_id'] ?? null,
            'qpaySenderInvoiceNo' => $arr['qpay_sender_invoice_no'] ?? null,
            'qpay_sender_invoice_no' => $arr['qpay_sender_invoice_no'] ?? null,
            'qpayQrText' => $arr['qpay_qr_text'] ?? null,
            'qpayQrImageBase64' => $arr['qpay_qr_image_base64'] ?? null,
            'qpayUrls' => is_array($arr['qpay_urls'] ?? null) ? $arr['qpay_urls'] : [],
            'createdAt' => $arr['created_at'] ?? null,
            'updatedAt' => $arr['updated_at'] ?? null,
        ];
    }
}
