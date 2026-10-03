<?php

namespace App\Actions\Pricing;

use App\Models\Period\PriceList;
use Illuminate\Support\Facades\DB;

final class SavePriceList
{
    public function handle(
        array $data,
        ?PriceList $list = null,
        ?int $expectedVersion = null,
    ): PriceList {
        return DB::connection('period')->transaction(function () use ($data, $list, $expectedVersion): PriceList {
            if ((bool) ($data['is_default'] ?? false)) {
                PriceList::query()
                    ->when($list, fn ($q) => $q->whereKeyNot($list->id))
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $attributes = [
                'name' => trim((string) $data['name']),
                'currency' => strtoupper((string) ($data['currency'] ?? 'TRY')),
                'vat_included' => (bool) ($data['vat_included'] ?? false),
                'is_default' => (bool) ($data['is_default'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ];

            return $list
                ? $list->updateWithVersion($attributes, $expectedVersion ?? (int) $list->version)
                : PriceList::query()->create($attributes);
        });
    }
}
