<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function analytics(Request $request): JsonResponse
    {
        $range = (string) $request->query('range', '30d');
        $tz = (string) $request->query('tz', 'Asia/Ulaanbaatar');

        $data = $this->dashboard->analytics($range, $tz);
        return ApiResponse::success($data['data'] ?? null);
    }
}
