<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BannerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function __construct(private readonly BannerService $banners)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->banners->listBanners(
            (int) $request->query('page', 1),
            (int) $request->query('limit', 20),
            $request->query('isActive') !== null ? filter_var($request->query('isActive'), FILTER_VALIDATE_BOOL) : null,
            true,
        );

        return ApiResponse::success(
            $data['data'] ?? null,
            200,
            ApiResponse::SUCCESS,
            null,
            $data['meta'] ?? null
        );
    }
}
