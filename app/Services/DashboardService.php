<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function analytics(string $range = '30d', string $tz = 'Asia/Ulaanbaatar'): array
    {
        $days = match ($range) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 365,
        };

        $to = Carbon::now($tz);
        $from = $to->copy()->subDays($days - 1)->startOfDay();

        $categoriesTotal = Category::query()->count();
        $productsTotal = Product::query()->count();
        $pendingArtistsTotal = DB::table('users')
            ->where('role', 'artist')
            ->where('artist_approval_status', 'pending')
            ->count();

        $ordersBase = Order::query()->whereBetween('created_at', [$from, $to]);
        $ordersTotal = (clone $ordersBase)->count();
        $ordersPaidTotal = (clone $ordersBase)->where('payment_status', 'paid')->count();

        $revenueProduct = (float) (clone $ordersBase)
            ->where('payment_status', 'paid')
            ->where('type', 'product')
            ->sum('total');

        // Booking module is intentionally not migrated.
        $revenueBooking = 0.0;
        $revenueTotal = $revenueProduct + $revenueBooking;
        $avgOrderValue = $ordersPaidTotal > 0 ? $revenueTotal / $ordersPaidTotal : 0.0;

        $revenueByDay = Order::query()
            ->selectRaw('DATE(created_at) as d')
            ->selectRaw('SUM(total) as total_revenue')
            ->selectRaw('SUM(CASE WHEN type = ? THEN total ELSE 0 END) as product_revenue', ['product'])
            ->selectRaw('SUM(CASE WHEN type = ? THEN total ELSE 0 END) as booking_revenue', ['booking'])
            ->selectRaw('COUNT(*) as orders_paid')
            ->whereBetween('created_at', [$from, $to])
            ->where('payment_status', 'paid')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $ordersByDay = Order::query()
            ->selectRaw('DATE(created_at) as d')
            ->selectRaw('COUNT(*) as orders_created')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $ordersStatus = Order::query()
            ->select('status as key')
            ->selectRaw('COUNT(*) as count')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();

        $ordersPaymentStatus = Order::query()
            ->select('payment_status as key')
            ->selectRaw('COUNT(*) as count')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('payment_status')
            ->orderByDesc('count')
            ->get();

        $topProductsMap = [];
        $paidProductOrders = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('payment_status', 'paid')
            ->where('type', 'product')
            ->get(['id', 'items']);

        foreach ($paidProductOrders as $order) {
            $items = is_array($order->items) ? $order->items : [];
            foreach ($items as $it) {
                if (!is_array($it)) {
                    continue;
                }

                $pid = (string) ($it['productId'] ?? '');
                if ($pid === '') {
                    continue;
                }

                $name = (string) ($it['name'] ?? 'Product');
                $qty = (float) ($it['quantity'] ?? 0);
                $price = (float) ($it['price'] ?? 0);

                if (!isset($topProductsMap[$pid])) {
                    $topProductsMap[$pid] = [
                        'productId' => $pid,
                        'name' => $name,
                        'quantity' => 0.0,
                        'revenue' => 0.0,
                        'ordersSet' => [],
                    ];
                }

                $topProductsMap[$pid]['quantity'] += $qty;
                $topProductsMap[$pid]['revenue'] += $price * $qty;
                $topProductsMap[$pid]['ordersSet'][(string) $order->id] = true;
            }
        }

        $topProducts = collect($topProductsMap)
            ->map(function (array $x): array {
                return [
                    'productId' => $x['productId'],
                    'name' => $x['name'],
                    'quantity' => $x['quantity'],
                    'revenue' => $x['revenue'],
                    'ordersCount' => count($x['ordersSet']),
                ];
            })
            ->sortByDesc('revenue')
            ->take(10)
            ->values();

        return [
            'data' => [
                'range' => [
                    'key' => $range,
                    'days' => $days,
                    'from' => $from->toIso8601String(),
                    'to' => $to->toIso8601String(),
                ],
                'timezone' => $tz,
                'totals' => [
                    'categoriesTotal' => $categoriesTotal,
                    'productsTotal' => $productsTotal,
                    'pendingArtistsTotal' => $pendingArtistsTotal,
                ],
                'kpis' => [
                    'revenueTotal' => $revenueTotal,
                    'revenueProduct' => $revenueProduct,
                    'ordersTotal' => $ordersTotal,
                    'ordersPaidTotal' => $ordersPaidTotal,
                    'avgOrderValue' => $avgOrderValue,
                ],
                'charts' => [
                    'revenueByDay' => $revenueByDay->map(fn ($x) => [
                        'date' => (string) $x->d,
                        'totalRevenue' => (float) ($x->total_revenue ?? 0),
                        'productRevenue' => (float) ($x->product_revenue ?? 0),
                        'bookingRevenue' => (float) ($x->booking_revenue ?? 0),
                        'ordersPaid' => (int) ($x->orders_paid ?? 0),
                    ])->values(),
                    'ordersByDay' => $ordersByDay->map(fn ($x) => [
                        'date' => (string) $x->d,
                        'ordersCreated' => (int) ($x->orders_created ?? 0),
                    ])->values(),
                    'ordersStatus' => $ordersStatus->map(fn ($x) => [
                        'key' => (string) ($x->key ?? 'unknown'),
                        'count' => (int) ($x->count ?? 0),
                    ])->values(),
                    'ordersPaymentStatus' => $ordersPaymentStatus->map(fn ($x) => [
                        'key' => (string) ($x->key ?? 'unknown'),
                        'count' => (int) ($x->count ?? 0),
                    ])->values(),
                    'topProducts' => $topProducts,
                ],
            ],
        ];
    }
}
