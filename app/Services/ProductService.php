<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class ProductService
{
    public function listProducts(int $page = 1, int $limit = 20, ?string $q = null, ?bool $isActive = null, ?string $categoryId = null): array
    {
        $query = Product::query();

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        if ($categoryId !== null && $categoryId !== '') {
            $query->where('category_id', $categoryId);
        }

        if ($q !== null && trim($q) !== '') {
            $term = trim($q);
            $query->where(function ($sub) use ($term): void {
                $sub->where('name', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%");
            });
        }

        $paginator = $query->latest('id')->paginate($limit, ['*'], 'page', $page);

        return [
            'data' => array_map(fn (Product $p) => $this->toDto($p), $paginator->items()),
            'meta' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pages' => $paginator->lastPage(),
            ],
        ];
    }

    public function getById(string $id, bool $forAdmin = false): array
    {
        $item = Product::query()->findOrFail($id);

        if (!$forAdmin && !$item->is_active) {
            abort(404);
        }

        return ['data' => $this->toDto($item)];
    }

    public function create(array $payload): array
    {
        $this->ensureCategoryExists($payload['categoryId'] ?? null);

        $slug = $this->makeUniqueSlug((string) ($payload['slug'] ?? $payload['name'] ?? 'product'));
        $image = $this->resolveImageValue($payload['image'] ?? null);

        $item = Product::query()->create([
            'name' => (string) $payload['name'],
            'slug' => $slug,
            'description' => $payload['description'] ?? null,
            'price' => (float) ($payload['price'] ?? 0),
            'image' => $image,
            'stock' => (float) ($payload['stock'] ?? 0),
            'unit' => (string) ($payload['unit'] ?? 'piece'),
            'category_id' => $payload['categoryId'] ?? null,
            'is_active' => (bool) ($payload['isActive'] ?? true),
        ]);

        return ['data' => $this->toDto($item)];
    }

    public function update(string $id, array $payload): array
    {
        $item = Product::query()->findOrFail($id);

        if (array_key_exists('name', $payload)) {
            $item->name = (string) $payload['name'];
        }
        if (array_key_exists('description', $payload)) {
            $item->description = $payload['description'];
        }
        if (array_key_exists('price', $payload)) {
            $item->price = (float) $payload['price'];
        }
        if (array_key_exists('image', $payload)) {
            $old = (string) ($item->image ?? '');
            $item->image = $this->resolveImageValue($payload['image']);
            if ($item->image !== $old) {
                $this->deleteLocalUpload($old);
            }
        }
        if (array_key_exists('stock', $payload)) {
            $item->stock = (float) $payload['stock'];
        }
        if (array_key_exists('unit', $payload)) {
            $item->unit = (string) $payload['unit'];
        }
        if (array_key_exists('categoryId', $payload)) {
            $this->ensureCategoryExists($payload['categoryId']);
            $item->category_id = $payload['categoryId'];
        }
        if (array_key_exists('isActive', $payload)) {
            $item->is_active = (bool) $payload['isActive'];
        }

        if (array_key_exists('slug', $payload) || array_key_exists('name', $payload)) {
            $base = (string) ($payload['slug'] ?? $payload['name'] ?? $item->slug);
            $item->slug = $this->makeUniqueSlug($base, $id);
        }

        $item->save();

        return ['data' => $this->toDto($item)];
    }

    private function toDto(Product $item): array
    {
        return [
            'id' => (string) $item->id,
            'name' => (string) $item->name,
            'slug' => (string) $item->slug,
            'description' => $item->description,
            'price' => (float) $item->price,
            'image' => $item->image,
            'stock' => (float) $item->stock,
            'unit' => (string) ($item->unit ?? 'piece'),
            'categoryId' => $item->category_id !== null ? (string) $item->category_id : null,
            'isActive' => (bool) $item->is_active,
            'audience' => $item->audience,
            'createdAt' => optional($item->created_at)?->toISOString(),
            'updatedAt' => optional($item->updated_at)?->toISOString(),
        ];
    }

    private function resolveImageValue(mixed $image): ?string
    {
        if ($image instanceof UploadedFile) {
            return $this->saveUploadedFile($image);
        }

        if ($image === null) {
            return null;
        }

        $value = trim((string) $image);
        return $value !== '' ? $value : null;
    }

    private function saveUploadedFile(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $safeExt = $extension !== '' ? Str::lower($extension) : 'bin';
        $name = Str::uuid()->toString() . '.' . $safeExt;

        $targetDir = public_path('uploads/products');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $file->move($targetDir, $name);

        return 'uploads/products/' . $name;
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

    public function delete(string $id): array
    {
        $item = Product::query()->findOrFail($id);
        $item->delete();

        return ['data' => $this->toDto($item)];
    }

    private function ensureCategoryExists(mixed $categoryId): void
    {
        if ($categoryId === null || $categoryId === '') {
            return;
        }

        $exists = Category::query()->whereKey((string) $categoryId)->exists();
        if (!$exists) {
            throw new RuntimeException('CATEGORY_NOT_FOUND');
        }
    }

    private function makeUniqueSlug(string $source, ?string $excludeId = null): string
    {
        $base = Str::slug($source);
        $base = $base !== '' ? $base : 'product';

        $candidate = $base;
        $i = 1;

        while (true) {
            $exists = Product::query()
                ->where('slug', $candidate)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists();

            if (!$exists) {
                return $candidate;
            }

            $i++;
            $candidate = "{$base}-{$i}";
        }
    }
}
