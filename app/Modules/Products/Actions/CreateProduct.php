<?php

namespace App\Modules\Products\Actions;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Models\Tax;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Barcode;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Products\Pricing\ProductPriceNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateProduct
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
        private ProductPriceNormalizer $prices,
    ) {}

    public function handle(CreateProductData $data): Product
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $code = $this->normalizeCode($data->code);
        $name = $this->normalizeName($data->name);
        $salePriceNet = $this->normalizePrice($data->salePriceNet, 'sale_price_net');
        $purchasePriceNet = $this->normalizePrice($data->purchasePriceNet, 'purchase_price_net');
        $barcodes = $this->normalizeBarcodes($data->primaryBarcode, $data->additionalBarcodes);

        $this->assertProductCodeAvailable($companyId, $code);
        $this->assertCategory($companyId, $data->categoryId);
        $this->assertUnit($companyId, $data->unitId);
        $this->assertTax($companyId, $data->taxId);
        $this->assertBarcodesAvailable($companyId, $barcodes);

        try {
            return DB::transaction(function () use (
                $companyId,
                $code,
                $name,
                $data,
                $salePriceNet,
                $purchasePriceNet,
                $barcodes,
            ): Product {
                $product = Product::query()->create([
                    'company_id' => $companyId,
                    'code' => $code,
                    'status' => ProductStatus::Active,
                    'name' => $name,
                    'category_id' => $data->categoryId,
                    'unit_id' => $data->unitId,
                    'tax_id' => $data->taxId,
                    'sale_price_net' => $salePriceNet,
                    'purchase_price_net' => $purchasePriceNet,
                ]);

                foreach ($barcodes as $index => $barcode) {
                    Barcode::query()->create([
                        'company_id' => $companyId,
                        'product_id' => $product->getKey(),
                        'barcode' => $barcode,
                        'is_primary' => $index === 0 && $data->primaryBarcode !== null,
                    ]);
                }

                $product->load(['category', 'unit', 'tax', 'barcodes']);

                $this->audit->record(
                    AuditAction::ProductCreated,
                    AuditTargetType::Product,
                    $product->getKey(),
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

    private function assertProductCodeAvailable(int $companyId, string $code): void
    {
        if (Product::query()
            ->where('company_id', $companyId)
            ->whereRaw('lower(code) = ?', [mb_strtolower($code)])
            ->exists()) {
            throw ValidationException::withMessages(['code' => 'Bu ürün kodu şirkette zaten kullanılıyor.']);
        }
    }

    private function assertCategory(int $companyId, ?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        if (! Category::query()
            ->where('company_id', $companyId)
            ->whereKey($categoryId)
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages(['category_id' => 'Aktif şirkete ait geçerli bir kategori seçilmelidir.']);
        }
    }

    private function assertUnit(int $companyId, int $unitId): void
    {
        if (! Unit::query()
            ->where('company_id', $companyId)
            ->whereKey($unitId)
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages(['unit_id' => 'Aktif şirkete ait geçerli bir birim seçilmelidir.']);
        }
    }

    private function assertTax(int $companyId, int $taxId): void
    {
        if (! Tax::query()
            ->where('company_id', $companyId)
            ->whereKey($taxId)
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages(['tax_id' => 'Aktif şirkete ait geçerli bir vergi tanımı seçilmelidir.']);
        }
    }

    /** @param list<string> $barcodes */
    private function assertBarcodesAvailable(int $companyId, array $barcodes): void
    {
        if ($barcodes === []) {
            return;
        }

        if (Barcode::query()
            ->where('company_id', $companyId)
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
            'category_id' => $product->category_id === null ? null : (int) $product->category_id,
            'unit_id' => (int) $product->unit_id,
            'tax_id' => (int) $product->tax_id,
            'sale_price_net' => (string) $product->sale_price_net,
            'purchase_price_net' => (string) $product->purchase_price_net,
            'barcodes' => $barcodes,
        ];
    }
}
