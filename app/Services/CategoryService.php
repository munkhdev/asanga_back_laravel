<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Str;

class CategoryService
{
    public function listCategories(int $page = 1, int $limit = 20, ?string $q = null, ?bool $isActive = null): array
    {
        $query = Category::query();

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
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
            'data' => $paginator->items(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pages' => $paginator->lastPage(),
            ],
        ];
    }

    public function getById(string $id): array
    {
        return ['data' => Category::query()->findOrFail($id)->toArray()];
    }

    public function create(array $payload): array
    {
        $slug = $this->makeUniqueSlug((string) ($payload['slug'] ?? $payload['name'] ?? 'category'));

        $item = Category::query()->create([
            'name' => (string) $payload['name'],
            'slug' => $slug,
            'icon' => $payload['icon'] ?? null,
            'image' => $payload['image'] ?? null,
            'is_active' => (bool) ($payload['isActive'] ?? true),
        ]);

        return ['data' => $item->toArray()];
    }

    public function update(string $id, array $payload): array
    {
        $item = Category::query()->findOrFail($id);

        if (array_key_exists('name', $payload)) {
            $item->name = (string) $payload['name'];
        }
        if (array_key_exists('icon', $payload)) {
            $item->icon = $payload['icon'];
        }
        if (array_key_exists('image', $payload)) {
            $item->image = $payload['image'];
        }
        if (array_key_exists('isActive', $payload)) {
            $item->is_active = (bool) $payload['isActive'];
        }

        if (array_key_exists('slug', $payload) || array_key_exists('name', $payload)) {
            $base = (string) ($payload['slug'] ?? $payload['name'] ?? $item->slug);
            $item->slug = $this->makeUniqueSlug($base, $id);
        }

        $item->save();

        return ['data' => $item->toArray()];
    }

    public function delete(string $id): array
    {
        $item = Category::query()->findOrFail($id);
        $item->delete();

        return ['data' => $item->toArray()];
    }

    private function makeUniqueSlug(string $source, ?string $excludeId = null): string
    {
        $base = Str::slug($source);
        $base = $base !== '' ? $base : 'category';

        $candidate = $base;
        $i = 1;

        while (true) {
            $exists = Category::query()
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
