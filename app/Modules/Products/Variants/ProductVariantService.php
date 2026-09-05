<?php

namespace App\Modules\Products\Variants;

use App\Modules\Products\Models\ProductFamily;
use App\Modules\Products\Models\ProductVariantRelation;
use App\Modules\Products\Models\ProductVariantValueAssignment;
use App\Modules\Products\Models\VariantDimension;
use App\Modules\Products\Models\VariantValue;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ProductVariantService
{
    /** @param array<string,mixed>|null $sharedContent */
    public function createFamily(int $companyId, string $code, string $name, ?array $sharedContent = null): ProductFamily
    {
        [$code, $name] = $this->familyIdentity($companyId, $code, $name);

        try {
            return ProductFamily::query()->create([
                'company_id' => $companyId,
                'code' => $code,
                'name' => $name,
                'shared_content' => $sharedContent,
            ]);
        } catch (QueryException $exception) {
            $this->throwConstraintViolation($exception, 'Product family code is already in use.');
        }
    }

    /** @param array<string,mixed>|null $sharedContent */
    public function updateFamily(int $companyId, int $familyId, string $code, string $name, ?array $sharedContent = null): ProductFamily
    {
        [$code, $name] = $this->familyIdentity($companyId, $code, $name);

        try {
            return DB::transaction(function () use ($companyId, $familyId, $code, $name, $sharedContent): ProductFamily {
                $family = $this->family($companyId, $familyId, true);
                $family->update(['code' => $code, 'name' => $name, 'shared_content' => $sharedContent]);

                return $family->refresh();
            });
        } catch (QueryException $exception) {
            $this->throwConstraintViolation($exception, 'Product family code is already in use.');
        }
    }

    public function deleteFamily(int $companyId, int $familyId): void
    {
        DB::transaction(function () use ($companyId, $familyId): void {
            $family = $this->family($companyId, $familyId, true);

            ProductVariantRelation::query()
                ->where('company_id', $companyId)
                ->where('product_family_id', $familyId)
                ->delete();

            $family->delete();
        });
    }

    public function addDimension(int $companyId, int $familyId, string $code, string $name, int $position = 0): VariantDimension
    {
        $this->family($companyId, $familyId);
        $code = strtolower(trim($code));
        $name = trim($name);
        if ($code === '' || $name === '' || $position < 0 || $position > 32767) {
            throw new DomainException('Variant dimension is invalid.');
        }

        try {
            return VariantDimension::query()->create([
                'company_id' => $companyId,
                'product_family_id' => $familyId,
                'code' => mb_substr($code, 0, 64),
                'name' => mb_substr($name, 0, 120),
                'position' => $position,
            ]);
        } catch (QueryException $exception) {
            $this->throwConstraintViolation($exception, 'Variant dimension code is already in use for this family.');
        }
    }

    public function addValue(int $companyId, int $familyId, int $dimensionId, string $code, string $label, int $position = 0): VariantValue
    {
        $dimension = VariantDimension::query()
            ->where('company_id', $companyId)
            ->where('product_family_id', $familyId)
            ->find($dimensionId);
        if (! $dimension instanceof VariantDimension) {
            throw new DomainException('Variant dimension was not found for company and family.');
        }

        $code = strtolower(trim($code));
        $label = trim($label);
        if ($code === '' || $label === '' || $position < 0 || $position > 32767) {
            throw new DomainException('Variant value is invalid.');
        }

        try {
            return VariantValue::query()->create([
                'company_id' => $companyId,
                'product_family_id' => $familyId,
                'variant_dimension_id' => $dimensionId,
                'code' => mb_substr($code, 0, 64),
                'label' => mb_substr($label, 0, 120),
                'position' => $position,
            ]);
        } catch (QueryException $exception) {
            $this->throwConstraintViolation($exception, 'Variant value code is already in use for this dimension.');
        }
    }

    /**
     * @param  array<array-key,mixed>  $dimensionValues  dimension_id => value_id
     */
    public function assignProduct(int $companyId, int $familyId, int $productId, array $dimensionValues): ProductVariantRelation
    {
        [$pairs, $signature] = $this->canonicalSelection($companyId, $familyId, $dimensionValues);

        try {
            return DB::transaction(function () use ($companyId, $familyId, $productId, $pairs, $signature): ProductVariantRelation {
                $this->family($companyId, $familyId, true);
                $product = DB::table('products')
                    ->where('company_id', $companyId)
                    ->where('id', $productId)
                    ->lockForUpdate()
                    ->first(['id']);
                if ($product === null) {
                    throw new DomainException('Variant product was not found for company.');
                }

                $existing = ProductVariantRelation::query()
                    ->where('company_id', $companyId)
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->first();
                if ($existing instanceof ProductVariantRelation) {
                    return $this->assertExactReplay($existing, $familyId, $signature, $pairs);
                }

                $owner = ProductVariantRelation::query()
                    ->where('company_id', $companyId)
                    ->where('product_family_id', $familyId)
                    ->where('variant_signature', $signature)
                    ->lockForUpdate()
                    ->first();
                if ($owner instanceof ProductVariantRelation) {
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
                    ProductVariantValueAssignment::query()->create([
                        'company_id' => $companyId,
                        'product_family_id' => $familyId,
                        'product_variant_relation_id' => $relationId,
                        'variant_dimension_id' => $dimensionId,
                        'variant_value_id' => $valueId,
                    ]);
                }

                return $relation->load('assignments');
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            $existing = ProductVariantRelation::query()
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->first();
            if ($existing instanceof ProductVariantRelation) {
                return $this->assertExactReplay($existing, $familyId, $signature, $pairs);
            }

            throw new DomainException('Variant assignment collided with a concurrent write.', 0, $exception);
        }
    }

    public function assignment(int $companyId, int $productId): ?ProductVariantRelation
    {
        return ProductVariantRelation::query()
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->with(['family', 'assignments.dimension', 'assignments.value'])
            ->first();
    }

    /** @return array{0:string,1:string} */
    private function familyIdentity(int $companyId, string $code, string $name): array
    {
        $code = strtoupper(trim($code));
        $name = trim($name);
        if ($companyId < 1 || $code === '' || $name === '') {
            throw new DomainException('Product family identity is required.');
        }

        return [mb_substr($code, 0, 64), mb_substr($name, 0, 191)];
    }

    /**
     * @param  array<array-key,mixed>  $dimensionValues
     * @return array{0:array<int,int>,1:string}
     */
    private function canonicalSelection(int $companyId, int $familyId, array $dimensionValues): array
    {
        if ($dimensionValues === []) {
            throw new DomainException('Variant assignment requires all family dimensions.');
        }

        $dimensions = VariantDimension::query()
            ->where('company_id', $companyId)
            ->where('product_family_id', $familyId)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        if ($dimensions === [] || count($dimensionValues) !== count($dimensions)) {
            throw new DomainException('Variant assignment must select exactly one value for every family dimension.');
        }

        $pairs = [];
        foreach ($dimensionValues as $dimensionId => $valueId) {
            if (! is_int($dimensionId) || ! is_int($valueId) || $dimensionId < 1 || $valueId < 1 || ! in_array($dimensionId, $dimensions, true)) {
                throw new DomainException('Variant dimension/value identity is invalid for family.');
            }
            $valid = VariantValue::query()
                ->where('company_id', $companyId)
                ->where('product_family_id', $familyId)
                ->where('variant_dimension_id', $dimensionId)
                ->whereKey($valueId)
                ->exists();
            if (! $valid) {
                throw new DomainException('Variant value does not belong to the selected family dimension.');
            }
            $pairs[$dimensionId] = $valueId;
        }
        ksort($pairs, SORT_NUMERIC);

        if (array_keys($pairs) !== $dimensions) {
            throw new DomainException('Variant assignment must select exactly one value for every family dimension.');
        }

        $canonical = implode('|', array_map(
            static fn (int $dimensionId, int $valueId): string => $dimensionId.':'.$valueId,
            array_keys($pairs),
            array_values($pairs),
        ));

        return [$pairs, hash('sha256', $canonical)];
    }

    /** @param array<int,int> $pairs */
    private function assertExactReplay(ProductVariantRelation $relation, int $familyId, string $signature, array $pairs): ProductVariantRelation
    {
        if ((int) $relation->product_family_id !== $familyId) {
            throw new DomainException('Product is already assigned to another product family.');
        }
        if (! hash_equals((string) $relation->variant_signature, $signature)) {
            throw new DomainException('Variant assignment drift detected.');
        }

        $persisted = ProductVariantValueAssignment::query()
            ->where('company_id', $relation->company_id)
            ->where('product_family_id', $familyId)
            ->where('product_variant_relation_id', $relation->getKey())
            ->orderBy('variant_dimension_id')
            ->get(['variant_dimension_id', 'variant_value_id'])
            ->mapWithKeys(static fn (ProductVariantValueAssignment $assignment): array => [
                (int) $assignment->variant_dimension_id => (int) $assignment->variant_value_id,
            ])->all();
        ksort($persisted, SORT_NUMERIC);
        if ($persisted !== $pairs) {
            throw new DomainException('Persisted variant assignment drift detected.');
        }

        return $relation->load('assignments');
    }

    private function family(int $companyId, int $familyId, bool $lock = false): ProductFamily
    {
        $query = ProductFamily::query()->where('company_id', $companyId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $family = $query->find($familyId);
        if (! $family instanceof ProductFamily) {
            throw new DomainException('Product family was not found for company.');
        }

        return $family;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23505' || (string) ($exception->errorInfo[0] ?? '') === '23505';
    }

    private function throwConstraintViolation(QueryException $exception, string $message): never
    {
        if ($this->isUniqueViolation($exception)) {
            throw new DomainException($message, 0, $exception);
        }

        throw $exception;
    }
}
