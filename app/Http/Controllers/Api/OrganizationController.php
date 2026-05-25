<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrganizationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $organization)
    {
    }

    public function show(): JsonResponse
    {
        $data = $this->organization->get();
        return ApiResponse::success($data['data'] ?? null);
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $this->organization->upsert($request->all());
        return ApiResponse::success($data['data'] ?? null);
    }

    public function delete(): JsonResponse
    {
        $data = $this->organization->delete();
        return ApiResponse::success($data['data'] ?? null);
    }
}
