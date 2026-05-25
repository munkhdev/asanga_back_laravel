<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;

class OrganizationService
{
    public function get(): array
    {
        $org = Organization::query()->where('singleton', 'default')->first();
        return ['data' => $org?->toArray()];
    }

    public function upsert(array $payload): array
    {
        $org = Organization::query()->firstOrCreate(
            ['singleton' => 'default'],
            ['shipping_price' => 0]
        );

        foreach (['name', 'introduction', 'story', 'logo', 'colorcover', 'phone1', 'phone2'] as $field) {
            if (array_key_exists($field, $payload)) {
                $org->{$field} = $payload[$field] === '' ? null : $payload[$field];
            }
        }

        if (array_key_exists('cover', $payload)) {
            $org->cover = is_array($payload['cover']) ? $payload['cover'] : null;
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

        return ['data' => $org->toArray()];
    }

    public function delete(): array
    {
        $org = Organization::query()->where('singleton', 'default')->first();
        if ($org) {
            $org->delete();
        }

        return ['data' => $org?->toArray()];
    }
}
