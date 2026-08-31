<?php

namespace App\Modules\Products\Actions;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Models\Tax;
use App\Modules\Products\Models\Barcode;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Products\Pricing\ProductPriceNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class UpdateProduct
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
        private ProductPriceNormalizer $prices,
    ) {}

    public function handle(int $productId, UpdateProductData $data): Product
    {
        $companyId = $this->companyId();
        $code = $this->normalizeCode($data->code);
        $name = $this->normalizeName($data->name);
        $brand = $this->normalizeBrand($data->brand);
        $salePriceNet = $this->normalizePrice($data->salePriceNet, 'sale_price_net');
        $purchasePriceNet = $this->normalizePrice($data->purchasePriceNet, 'purchase_price_net');
        $barcodes = $this->normalizeBarcodes($data->primaryBarcode, $data->additionalBarcodes);

        try {
            return DB::transaction(function () use (
                $companyId,
                $productId,
                $data,
                $code,
                $name,
                $brand,
                $salePriceNet,
                $purchasePriceNet,
                $barcodes,
            ): Product {
                $product = Product::query()
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->findOrFail($productId);
                $product->load('barcodes');

                $this->assertProductCodeAvailable($companyId, $code, $productId);
                $this->assertCategory($companyId, $data->categoryId, $product);
                $this->assertUnit($companyId, $data->unitId, $product);
                $this->assertTax($companyId, $data->taxId, $product);
                $this->assertBarcodesAvailable($companyId, $barcodes, $productId);

                $before = $this->snapshot($product);

                $product->fill([
                    'code' => $code,
                    'status' => $data->status,
                    'name' => $name,
                    'brand' => $brand,
                    'category_id' => $data->categoryId,
                    'unit_id' => $data->unitId,
                    'tax_id' => $data->taxId,
                    'sale_price_net' => $salePriceNet,
                    'purchase_price_net' => $purchasePriceNet,
                ]);
                $product->save();

                Barcode::query()
                    ->where('company_id', $companyId)
                    ->where('product_id', $productId)
                    ->delete();

                foreach ($barcodes as $index => $barcode) {
                    Barcode::query()->create([
                        'company_id' => $companyId,
                        'product_id' => $productId,
                        'barcode' => $barcode,
                        'is_primary' => $index === 0 && $data->primaryBarcode !== null,
                    ]);
                }

                $product->load(['category', 'unit', 'tax', 'barcodes']);

                $this->audit->record(
                    AuditAction::ProductUpdated,
                    AuditTargetType::Product,
                    $product->getKey(),
                    before: $before,
                    after: $this->snapshot($product),
                );

                return $product;
            });
        } catch (QueryException $exception) {
            $this->throwIdentityConflict($exception);
        }
    }

    private function normalizeCode(string $raw): string
    {
        $code = mb_strtoupper(trim($raw));
        if (preg_match('/^[A-Z0-9]+(?:[._-][A-Z0-9]+)*$/', $code) !== 1 || mb_strlen($code) > 64) {
            throw ValidationException::withMessages([
                'code' => 'Ürün kodu 1-64 karakter olmalı ve yalnız harf, rakam, nokta, alt çizgi veya tire içermelidir.',
            ]);
        }

        return $code;
    }

    private function normalizeName(string $raw): string
    {
        $name = trim($raw);
        if ($name === '' || mb_strlen($name) > 200) {
            throw ValidationException::withMessages(['name' => 'Ürün adı 1-200 karakter olmalıdır.']);
        }

        return $name;
    }

    private function normalizeBrand(?string $raw): ?string
    {
        $brand = trim((string) $raw);
        if ($brand === '') {
            return null;
        }
        if (mb_strlen($brand) > 160) {
            throw ValidationException::withMessages(['brand' => 'Marka 160 karakteri aşamaz.']);
        }

        return $brand;
    }

    private function normalizePrice(string $raw, string $field): string
    {
        try {
            return $this->prices->normalize($raw);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }
    }

    /**
     * @param  list<string>  $additional
     * @return list<string>
     */
    private function normalizeBarcodes(?string $primary, array $additional): array
    {
        $values = [];
        if ($primary !== null) {
            $values[] = $primary;
        }
        array_push($values, ...$additional);

        $normalized = [];
        foreach ($values as $value) {
            $barcode = trim($value);
            if ($barcode === '' || mb_strlen($barcode) > 128 || preg_match('/[\x00-\x1F\x7F]/', $barcode) === 1) {
                throw ValidationException::withMessages(['barcodes' => 'Barkod 1-128 karakter olmalı ve kontrol karakteri içermemelidir.']);
            }
            if (isset($normalized[$barcode])) {
                throw ValidationException::withMessages(['barcodes' => 'Aynı barkod ürün üzerinde birden fazla kez kullanılamaz.']);
            }

            $normalized[$barcode] = true;
        }

        return array_keys($normalized);
    }

    private function assertProductCodeAvailable(int $companyId, string $code, int $ignoreProductId): void
    {
        if (Product::query()
            ->where('company_id', $companyId)
            ->where('id', '<>', $ignoreProductId)
            ->whereRaw('lower(code) = ?', [mb_strtolower($code)])
            ->exists()) {
            throw ValidationException::withMessages(['code' => 'Bu ürün kodu şirkette zaten kullanılıyor.']);
        }
    }

    private function assertCategory(int $companyId, ?int $categoryId, Product $product): void
    {
        if ($categoryId === null) {
            return;
        }

        $query = Category::query()
            ->where('company_id', $companyId)
            ->whereKey($categoryId);

        if ((int) $product->category_id !== $categoryId) {
            $query->where('is_active', true);
        }

        if (! $query->exists()) {
            throw ValidationException::withMessages(['category_id' => 'Aktif şirkete ait geçerli bir kategori seçilmelidir.']);
        }
    }

    private function assertUnit(int $companyId, int $unitId, Product $product): void
    {
        $query = Unit::query()
            ->where('company_id', $companyId)
            ->whereKey($unitId);

        if ((int) $product->unit_id !== $unitId) {
            $query->where('is_active', true);
        }

        if (! $query->exists()) {
            throw ValidationException::withMessages(['unit_id' => 'Aktif şirkete ait geçerli bir birim seçilmelidir.']);
        }
    }

    private function assertTax(int $companyId, int $taxId, Product $product): void
    {
        $query = Tax::query()
            ->where('company_id', $companyId)
            ->whereKey($taxId);

        if ((int) $product->tax_id !== $taxId) {
            $query->where('is_active', true);
        }

        if (! $query->exists()) {
            throw ValidationException::withMessages(['tax_id' => 'Aktif şirkete ait geçerli bir vergi tanımı seçilmelidir.']);
        }
    }

    /** @param list<string> $barcodes */
    private function assertBarcodesAvailable(int $companyId, array $barcodes, int $ignoreProductId): void
    {
        if ($barcodes === []) {
            return;
        }

        if (Barcode::query()
            ->where('company_id', $companyId)
            ->where('product_id', '<>', $ignoreProductId)
            ->whereIn('barcode', $barcodes)
            ->exists()) {
            throw ValidationException::withMessages(['barcodes' => 'Barkodlardan en az biri şirkette başka bir üründe kullanılıyor.']);
        }
    }

    private function throwIdentityConflict(QueryException $exception): never
    {
        if ((string) $exception->getCode() !== '23505') {
            throw $exception;
        }

        $message = (string) ($exception->errorInfo[2] ?? $exception->getMessage());
        if (str_contains($message, 'barcodes_company_barcode_unique')
            || str_contains($message, 'barcodes_product_primary_unique')) {
            throw ValidationException::withMessages(['barcodes' => 'Barkod kimliği başka bir ürün tarafından kullanılıyor.']);
        }

        throw ValidationException::withMessages(['code' => 'Bu ürün kodu şirkette zaten kullanılıyor.']);
    }

    /**
     * @return array{
     *     code:string,
     *     status:string,
     *     name:string,
     *     brand:?string,
     *     category_id:?int,
     *     unit_id:int,
     *     tax_id:int,
     *     sale_price_net:string,
     *     purchase_price_net:string,
     *     barcodes:list<string>
     * }
     */
    private function snapshot(Product $product): array
    {
        /** @var list<string> $barcodes */
        $barcodes = $product->barcodes
            ->pluck('barcode')
            ->map(static fn (mixed $value): string => (string) $value)
            ->values()
            ->all();

        return [
            'code' => (string) $product->code,
            'status' => $product->statusEnum()->value,
            'name' => (string) $product->name,
            'brand' => $product->brand === null ? null : (string) $product->brand,
            'category_id' => $product->category_id === null ? null : (int) $product->category_id,
            'unit_id' => (int) $product->unit_id,
            'tax_id' => (int) $product->tax_id,
            'sale_price_net' => (string) $product->sale_price_net,
            'purchase_price_net' => (string) $product->purchase_price_net,
            'barcodes' => $barcodes,
        ];
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();
        if (! is_int($companyId)) {
            throw new LogicException('Product update requires a persisted active company.');
        }

        return $companyId;
    }
}
