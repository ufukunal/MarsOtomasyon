<?php

namespace App\Actions\Products;

use App\Models\Period\ConfigDefinition;
use App\Models\Period\ConfigOption;
use App\Models\Period\Product;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveConfigDefinition
{
    /**
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>> $options
     */
    public function handle(
        Product $product,
        array $data,
        array $options,
        ?ConfigDefinition $definition = null,
        ?int $expectedVersion = null,
    ): ConfigDefinition {
        MutationAuthorizer::authorize('products.update');
        PeriodContext::ensureWritable();
        if ((bool) ($data['is_required'] ?? false) && $options === []) {
            throw ValidationException::withMessages([
                'options' => 'Zorunlu seçim grubunda en az bir seçenek olmalıdır.',
            ]);
        }

        if (collect($options)->where('is_default', true)->count() > 1) {
            throw ValidationException::withMessages([
                'options' => 'Bir grupta en fazla bir varsayılan seçenek olabilir.',
            ]);
        }

        return DB::connection('period')->transaction(function () use ($product, $data, $options, $definition, $expectedVersion): ConfigDefinition {
            $attributes = [
                'product_id' => $product->id,
                'name' => trim((string) $data['name']),
                'is_required' => (bool) ($data['is_required'] ?? false),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ];

            $definition = $definition
                ? $definition->updateWithVersion($attributes, $expectedVersion ?? (int) $definition->version)
                : ConfigDefinition::query()->create($attributes);

            $keep = [];

            foreach ($options as $index => $option) {
                $componentId = $option['component_product_id'] ?? null;

                if ($componentId) {
                    $component = Product::query()->findOrFail($componentId);

                    if (! $component->is_active) {
                        throw ValidationException::withMessages([
                            'options' => 'Pasif ürün konfigüratör seçeneği olamaz.',
                        ]);
                    }
                }

                $optionAttributes = [
                    'component_product_id' => $componentId ?: null,
                    'label' => trim((string) $option['label']),
                    'sort_order' => (int) ($option['sort_order'] ?? $index),
                    'is_default' => (bool) ($option['is_default'] ?? false),
                ];

                if (! empty($option['id'])) {
                    $row = ConfigOption::query()
                        ->where('config_definition_id', $definition->id)
                        ->findOrFail((int) $option['id']);

                    $row = $row->updateWithVersion(
                        $optionAttributes,
                        (int) ($option['version'] ?? $row->version),
                    );
                } else {
                    $row = ConfigOption::query()->create([
                        'config_definition_id' => $definition->id,
                        ...$optionAttributes,
                    ]);
                }

                $keep[] = $row->id;
            }

            ConfigOption::query()
                ->where('config_definition_id', $definition->id)
                ->when($keep !== [], fn ($q) => $q->whereNotIn('id', $keep))
                ->delete();

            return $definition->refresh();
        });
    }
}
