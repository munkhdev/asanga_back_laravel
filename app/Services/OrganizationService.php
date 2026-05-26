<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class OrganizationService
{
    public function get(): array
    {
        $org = Organization::query()->where('singleton', 'default')->first();
        return ['data' => $org ? $this->toDto($org) : null];
    }

    public function upsert(array $payload): array
    {
        $org = Organization::query()->firstOrCreate(
            ['singleton' => 'default'],
            ['shipping_price' => 0]
        );

        foreach (['name', 'introduction', 'story', 'phone1', 'phone2'] as $field) {
            if (array_key_exists($field, $payload)) {
                $org->{$field} = $payload[$field] === '' ? null : $payload[$field];
            }
        }

        foreach (['logo', 'colorcover'] as $field) {
            if (array_key_exists($field, $payload)) {
                $org->{$field} = $this->resolveUploadValue($payload[$field]);
            }
        }

        if (array_key_exists('cover', $payload) || array_key_exists('coverFiles', $payload)) {
            $cover = array_key_exists('cover', $payload)
                ? $this->normalizeStringList($payload['cover'])
                : $this->normalizeStringList($org->cover);

            foreach ($this->normalizeFileList($payload['coverFiles'] ?? []) as $file) {
                $cover[] = $this->saveUploadedFile($file);
            }

            $org->cover = count($cover) > 0 ? array_values($cover) : null;
        }

        if (array_key_exists('social', $payload)) {
            $org->social = is_array($payload['social']) ? $payload['social'] : null;
        }

        if (array_key_exists('stats', $payload)) {
            $org->stats = is_array($payload['stats']) ? $payload['stats'] : null;
        }

        if (array_key_exists('shippingPrice', $payload)) {
            $n = (float) $payload['shippingPrice'];
            $org->shipping_price = max(0, $n);
        }

        $org->save();

        return ['data' => $this->toDto($org)];
    }

    public function delete(): array
    {
        $org = Organization::query()->where('singleton', 'default')->first();
        if ($org) {
            $org->delete();
        }

        return ['data' => $org ? $this->toDto($org) : null];
    }

    private function toDto(Organization $org): array
    {
        return [
            'id' => (string) $org->id,
            'name' => $org->name,
            'introduction' => $org->introduction,
            'story' => $org->story,
            'logo' => $org->logo,
            'colorcover' => $org->colorcover,
            'cover' => $this->normalizeStringList($org->cover),
            'phone1' => $org->phone1,
            'phone2' => $org->phone2,
            'shippingPrice' => (float) ($org->shipping_price ?? 0),
            'social' => is_array($org->social) ? $org->social : null,
            'stats' => is_array($org->stats) ? $org->stats : null,
            'createdAt' => optional($org->created_at)?->toISOString(),
            'updatedAt' => optional($org->updated_at)?->toISOString(),
        ];
    }

    private function resolveUploadValue(mixed $value): ?string
    {
        if ($value instanceof UploadedFile) {
            return $this->saveUploadedFile($value);
        }

        $text = trim((string) ($value ?? ''));
        if ($this->looksLikePhpTempUploadPath($text)) {
            return null;
        }

        return $text !== '' ? $text : null;
    }

    private function looksLikePhpTempUploadPath(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $normalized = str_replace('\\', '/', $value);
        return (bool) preg_match('#(^|/)(tmp|temp)/php[A-Za-z0-9]+$#', $normalized)
            || (bool) preg_match('#^[A-Z]:/[^\r\n]*/php[A-Za-z0-9]+$#i', $normalized);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            $value = $value === null ? [] : [$value];
        }

        $items = array_map(
            fn ($item) => trim((string) $item),
            $value,
        );

        return array_values(array_filter(
            $items,
            fn ($item) => $item !== '' && !$this->looksLikePhpTempUploadPath($item)
        ));
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function normalizeFileList(mixed $value): array
    {
        $items = is_array($value) ? $value : [$value];
        return array_values(array_filter(
            $items,
            fn ($item) => $item instanceof UploadedFile && $item->isValid()
        ));
    }

    private function saveUploadedFile(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $safeExt = $extension !== '' ? Str::lower($extension) : 'bin';
        $name = Str::uuid()->toString() . '.' . $safeExt;

        $targetDir = public_path('uploads/organization');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $file->move($targetDir, $name);

        return 'uploads/organization/' . $name;
    }
}
