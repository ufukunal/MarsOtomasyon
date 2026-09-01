<?php

namespace App\Modules\Products\Variants;

use App\Modules\Products\Models\ProductFamily;
use App\Modules\Products\Models\ProductVariantRelation;
use App\Modules\Products\Models\VariantDimension;
use App\Modules\Products\Models\VariantValue;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ProductVariantService
{
    /** @param array<string,mixed>|null $sharedContent */
    public function createFamily(int $companyId, string $code, string $name, ?array $sharedContent = null): ProductFamily
    {
        $code = strtoupper(trim($code));
        $name = trim($name);
        if ($companyId < 1 || $code === '' || $name === '') {
            throw new DomainException('Product family identity is required.');
        }

        return ProductFamily::query()->create([
            'company_id' => $companyId,
            'code' => mb_substr($code, 0, 64),
            'name' => mb_substr($name, 0, 191),
            'shared_content' => $sharedContent,
        ]);
    }

    public function addDimension(int $companyId, int $familyId, string $code, string $name, int $position = 0): VariantDimension
    {
        $this->assertFamily($companyId, $familyId);
        $code = strtolower(trim($code));
        $name = trim($name);
        if ($code === '' || $name === '' || $position < 0 || $position > 32767) {
            throw new DomainException('Variant dimension is invalid.');
        }

        return VariantDimension::query()->create([
            'company_id' => $companyId,
            'product_family_id' => $familyId,
            'code' => mb_substr($code, 0, 64),
            'name' => mb_substr($name, 0, 120),
            'position' => $position,
        ]);
    }

    public function addValue(int $companyId, int $dimensionId, string $code, string $label, int $position = 0): VariantValue
    {
        $dimension = VariantDimension::query()
            ->where('company_id', $companyId)
            ->findOrFail($dimensionId);
        $code = strtolower(trim($code));
        $label = trim($label);
        if ($code === '' || $label === '' || $position < 0 || $position > 32767) {
            throw new DomainException('Variant value is invalid.');
        }

        return VariantValue::query()->create([
            'company_id' => $companyId,
            'variant_dimension_id' => $dimension->getKey(),
            'code' => mb_substr($code, 0, 64),
            'label' => mb_substr($label, 0, 120),
            'position' => $position,
        ]);
    }

    /** @param array<int,int> $dimensionValues dimension_id => value_id */
    public function attachProduct(int $companyId, int $familyId, int $productId, array $dimensionValues): ProductVariantRelation
    {
        if ($dimensionValues === []) {
            throw new DomainException('At least one variant dimension value is required.');
        }

        return DB::transaction(function () use ($companyId, $familyId, $productId, $dimensionValues): ProductVariantRelation {
            $this->assertFamily($companyId, $familyId);
            $product = DB::table('products')->where('company_id', $companyId)->where('id', $productId)->lockForUpdate()->first();
            if ($product === null) {
                throw new DomainException('Variant product was not found for company.');
            }

            $pairs = [];
            foreach ($dimensionValues as $dimensionId => $valueId) {
                if (! is_int($dimensionId) || ! is_int($valueId) || $dimensionId < 1 || $valueId < 1) {
                    throw new DomainException('Variant dimension/value identity is invalid.');
                }
                $value = DB::table('variant_values as vv')
                    ->join('variant_dimensions as vd', 'vd.id', '=', 'vv.variant_dimension_id')
                    ->where('vv.company_id', $companyId)
                    ->where('vd.company_id', $companyId)
                    ->where('vd.product_family_id', $familyId)
                    ->where('vd.id', $dimensionId)
                    ->where('vv.id', $valueId)
                    ->first(['vd.id as dimension_id', 'vv.id as value_id']);
                if ($value === null) {
                    throw new DomainException('Variant value does not belong to the selected family dimension.');
                }
                $pairs[(int) $value->dimension_id] = (int) $value->value_id;
            }
            ksort($pairs, SORT_NUMERIC);
            $signature = hash('sha256', implode('|', array_map(
                static fn (int $dimensionId, int $valueId): string => $dimensionId.':'.$valueId,
                array_keys($pairs),
                array_values($pairs),
            )));

            $existingProduct = ProductVariantRelation::query()
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();
            if ($existingProduct instanceof ProductVariantRelation) {
                if ((int) $existingProduct->product_family_id === $familyId && hash_equals((string) $existingProduct->variant_signature, $signature)) {
                    return $existingProduct;
                }

                throw new DomainException('Product is already assigned to another family or variant combination.');
            }

            $signatureOwner = ProductVariantRelation::query()
                ->where('company_id', $companyId)
                ->where('product_family_id', $familyId)
                ->where('variant_signature', $signature)
                ->lockForUpdate()
                ->first();
            if ($signatureOwner instanceof ProductVariantRelation) {
                throw new DomainException('Variant combination is already assigned to another product.');
            }

            $relation = ProductVariantRelation::query()->create([
                'company_id' => $companyId,
                'product_family_id' => $familyId,
                'product_id' => $productId,
                'variant_signature' => $signature,
            ]);
            $relationId = $relation->getKey();
            if (! is_int($relationId)) {
                throw new RuntimeException('Variant relation persistence did not return an integer key.');
            }
            foreach ($pairs as $dimensionId => $valueId) {
                DB::table('product_variant_value_assignments')->insert([
                    'company_id' => $companyId,
                    'product_variant_relation_id' => $relationId,
                    'variant_dimension_id' => $dimensionId,
                    'variant_value_id' => $valueId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $relation;
        });
    }

    public function mapMarketplace(
        int $companyId,
        int $relationId,
        string $provider,
        ?string $parentExternalId,
        string $variantExternalId,
    ): int {
        $provider = strtolower(trim($provider));
        $variantExternalId = trim($variantExternalId);
        $parentExternalId = $parentExternalId === null ? null : trim($parentExternalId);
        if ($provider === '' || $variantExternalId === '') {
            throw new DomainException('Marketplace variant mapping identity is required.');
        }

        return DB::transaction(function () use ($companyId, $relationId, $provider, $parentExternalId, $variantExternalId): int {
            $relation = ProductVariantRelation::query()->where('company_id', $companyId)->lockForUpdate()->find($relationId);
            if (! $relation instanceof ProductVariantRelation) {
                throw new DomainException('Variant relation was not found for company.');
            }

            $existing = DB::table('marketplace_variant_mappings')
                ->where('company_id', $companyId)
                ->where('provider', $provider)
                ->where('product_variant_relation_id', $relationId)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                if ((string) $existing->variant_external_id !== $variantExternalId || ($existing->parent_external_id === null ? null : (string) $existing->parent_external_id) !== $parentExternalId) {
                    throw new DomainException('Marketplace variant mapping drift detected.');
                }

                return (int) $existing->id;
            }

            $externalOwner = DB::table('marketplace_variant_mappings')
                ->where('company_id', $companyId)
                ->where('provider', $provider)
                ->where('variant_external_id', $variantExternalId)
                ->lockForUpdate()
                ->first();
            if ($externalOwner !== null) {
                throw new DomainException('Marketplace variant identity is already mapped to another product.');
            }

            return (int) DB::table('marketplace_variant_mappings')->insertGetId([
                'company_id' => $companyId,
                'product_variant_relation_id' => $relationId,
                'provider' => mb_substr($provider, 0, 64),
                'parent_external_id' => $parentExternalId === null || $parentExternalId === '' ? null : mb_substr($parentExternalId, 0, 191),
                'variant_external_id' => mb_substr($variantExternalId, 0, 191),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function assertFamily(int $companyId, int $familyId): ProductFamily
    {
        $family = ProductFamily::query()->where('company_id', $companyId)->find($familyId);
        if (! $family instanceof ProductFamily) {
            throw new DomainException('Product family was not found for company.');
        }

        return $family;
    }
}
