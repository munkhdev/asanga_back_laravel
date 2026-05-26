<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrganizationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

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
        $payload = $request->except(['_method', 'logo', 'colorcover', 'coverFiles']);

        foreach (['social', 'stats'] as $key) {
            if (!array_key_exists($key, $payload) || !is_string($payload[$key])) {
                continue;
            }

            $raw = trim($payload[$key]);
            if ($raw === '' || $raw === 'null') {
                $payload[$key] = null;
                continue;
            }

            $decoded = json_decode($raw, true);
            $payload[$key] = is_array($decoded) ? $decoded : null;
        }

        if (array_key_exists('cover', $payload)) {
            $cover = $payload['cover'];
            if ($cover === 'null' || $cover === '') {
                $payload['cover'] = null;
            } elseif (!is_array($cover)) {
                $payload['cover'] = [$cover];
            }
        }

        $logo = $this->firstUploadedFile($request->file('logo'));
        if ($logo) {
            $payload['logo'] = $logo;
        }

        $colorcover = $this->firstUploadedFile($request->file('colorcover'));
        if ($colorcover) {
            $payload['colorcover'] = $colorcover;
        }

        $coverFiles = array_values(array_filter(
            is_array($request->file('coverFiles')) ? $request->file('coverFiles') : [$request->file('coverFiles')],
            fn ($file) => $file instanceof UploadedFile && $file->isValid()
        ));
        if (count($coverFiles) > 0) {
            $payload['coverFiles'] = $coverFiles;
        }

        $data = $this->organization->upsert($payload);
        return ApiResponse::success($data['data'] ?? null);
    }

    private function firstUploadedFile(mixed $value): ?UploadedFile
    {
        if ($value instanceof UploadedFile && $value->isValid()) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if ($item instanceof UploadedFile && $item->isValid()) {
                    return $item;
                }
            }
        }

        return null;
    }

    public function delete(): JsonResponse
    {
        $data = $this->organization->delete();
        return ApiResponse::success($data['data'] ?? null);
    }
}
