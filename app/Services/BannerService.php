<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Banner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class BannerService
{
    public function listBanners(int $page = 1, int $limit = 20, ?bool $isActive = null, bool $publicOnly = false): array
    {
        $query = Banner::query();

        if ($publicOnly && $isActive === null) {
            $query->where('is_active', true);
        } elseif ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        $paginator = $query->latest('id')->paginate($limit, ['*'], 'page', $page);

        return [
            'data' => array_map(fn (Banner $b) => $this->toDto($b), $paginator->items()),
            'meta' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pages' => $paginator->lastPage(),
            ],
        ];
    }

    public function create(array $payload): array
    {
        $filePath = $this->saveUploadedFile($payload['file'] ?? null);

        $item = Banner::query()->create([
            'title' => (string) ($payload['title'] ?? ''),
            'description' => $payload['description'] ?? null,
            'file' => $filePath,
            'file_type' => (string) ($payload['fileType'] ?? 'image'),
            'is_active' => (bool) ($payload['isActive'] ?? true),
        ]);

        return ['data' => $this->toDto($item)];
    }

    public function update(string $id, array $payload): array
    {
        $item = Banner::query()->findOrFail($id);

        if (array_key_exists('title', $payload)) {
            $item->title = (string) $payload['title'];
        }

        if (array_key_exists('description', $payload)) {
            $item->description = $payload['description'];
        }

        if (array_key_exists('isActive', $payload)) {
            $item->is_active = (bool) $payload['isActive'];
        }

        if (array_key_exists('fileType', $payload)) {
            $item->file_type = (string) $payload['fileType'];
        }

        if (array_key_exists('file', $payload) && $payload['file'] instanceof UploadedFile) {
            $old = (string) $item->file;
            $item->file = $this->saveUploadedFile($payload['file']);
            $this->deleteLocalUpload($old);
        }

        $item->save();

        return ['data' => $this->toDto($item)];
    }

    public function delete(string $id): array
    {
        $item = Banner::query()->findOrFail($id);
        $old = (string) $item->file;
        $item->delete();
        $this->deleteLocalUpload($old);

        return ['data' => $this->toDto($item)];
    }

    private function toDto(Banner $item): array
    {
        return [
            'id' => (string) $item->id,
            'title' => (string) $item->title,
            'description' => $item->description,
            'file' => (string) $item->file,
            'fileType' => (string) $item->file_type,
            'isActive' => (bool) $item->is_active,
            'createdAt' => optional($item->created_at)?->toISOString(),
            'updatedAt' => optional($item->updated_at)?->toISOString(),
        ];
    }

    private function saveUploadedFile(mixed $file): string
    {
        if (!$file instanceof UploadedFile) {
            throw new \InvalidArgumentException('FILE_REQUIRED');
        }

        $extension = $file->getClientOriginalExtension();
        $safeExt = $extension !== '' ? Str::lower($extension) : 'bin';
        $name = Str::uuid()->toString() . '.' . $safeExt;

        $targetDir = public_path('uploads/banners');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $file->move($targetDir, $name);

        return 'uploads/banners/' . $name;
    }

    private function deleteLocalUpload(string $relativePath): void
    {
        $safe = str_replace(['..', '\\'], ['', '/'], trim($relativePath));
        if ($safe === '' || !str_starts_with($safe, 'uploads/')) {
            return;
        }

        $fullPath = public_path($safe);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
