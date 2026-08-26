<?php

namespace App\Modules\Products;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\Tax;
use App\Modules\Products\Actions\CreateProduct;
use App\Modules\Products\Actions\CreateProductData;
use App\Modules\Products\Actions\UpdateProduct;
use App\Modules\Products\Actions\UpdateProductData;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Products\Search\ProductSearchQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use LogicException;

final readonly class ProductController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private CreateProduct $createProduct,
        private UpdateProduct $updateProduct,
        private ProductSearchQuery $productSearch,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', ProductStatus::Active->value, ProductStatus::Inactive->value], true)) {
            $status = 'all';
        }

        $statusFilter = $status === 'all' ? null : ProductStatus::from($status);
        $query = $this->productSearch
            ->build($this->companyId(), $search, $statusFilter)
            ->with(['category', 'unit', 'tax', 'barcodes']);

        $products = $query
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->orderBy('code')
            ->paginate(50)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'search' => $search,
            'statusFilter' => $status,
        ]);
    }

    public function create(): View
    {
        return view('products.form', [
            'product' => null,
            'productStatuses' => ProductStatus::cases(),
            'categories' => $this->categories(),
            'units' => $this->units(),
            'taxes' => $this->taxes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules(includeStatus: false));

        $product = $this->createProduct->handle(new CreateProductData(
            code: (string) $validated['code'],
            name: (string) $validated['name'],
            categoryId: $this->nullableInt($validated['category_id'] ?? null),
            unitId: (int) $validated['unit_id'],
            taxId: (int) $validated['tax_id'],
            salePriceNet: (string) $validated['sale_price_net'],
            purchasePriceNet: (string) $validated['purchase_price_net'],
            primaryBarcode: $this->nullableString($validated['primary_barcode'] ?? null),
            additionalBarcodes: $this->barcodeLines($validated['additional_barcodes'] ?? null),
        ));

        return redirect()->route('inventory.products.show', $product->getKey())
            ->with('status', 'Ürün oluşturuldu.');
    }

    public function show(int $product): View
    {
        $productModel = $this->product($product);
        $productModel->load(['category', 'unit', 'tax', 'barcodes']);

        return view('products.show', ['product' => $productModel]);
    }

    public function edit(int $product): View
    {
        $productModel = $this->product($product);
        $productModel->load('barcodes');

        return view('products.form', [
            'product' => $productModel,
            'productStatuses' => ProductStatus::cases(),
            'categories' => $this->categories($productModel),
            'units' => $this->units($productModel),
            'taxes' => $this->taxes($productModel),
        ]);
    }

    public function update(Request $request, int $product): RedirectResponse
    {
        $validated = $request->validate($this->rules(includeStatus: true));

        $updated = $this->updateProduct->handle($product, new UpdateProductData(
            code: (string) $validated['code'],
            status: ProductStatus::from((string) $validated['status']),
            name: (string) $validated['name'],
            categoryId: $this->nullableInt($validated['category_id'] ?? null),
            unitId: (int) $validated['unit_id'],
            taxId: (int) $validated['tax_id'],
            salePriceNet: (string) $validated['sale_price_net'],
            purchasePriceNet: (string) $validated['purchase_price_net'],
            primaryBarcode: $this->nullableString($validated['primary_barcode'] ?? null),
            additionalBarcodes: $this->barcodeLines($validated['additional_barcodes'] ?? null),
        ));

        return redirect()->route('inventory.products.show', $updated->getKey())
            ->with('status', 'Ürün güncellendi.');
    }

    /** @return array<string, mixed> */
    private function rules(bool $includeStatus): array
    {
        $rules = [
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'unit_id' => ['required', 'integer', 'min:1'],
            'tax_id' => ['required', 'integer', 'min:1'],
            'sale_price_net' => ['required', 'decimal:0,6', 'min:0'],
            'purchase_price_net' => ['required', 'decimal:0,6', 'min:0'],
            'primary_barcode' => ['nullable', 'string', 'max:128'],
            'additional_barcodes' => ['nullable', 'string', 'max:8000'],
        ];

        if ($includeStatus) {
            $rules['status'] = ['required', Rule::enum(ProductStatus::class)];
        }

        return $rules;
    }

    private function product(int $id): Product
    {
        return Product::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);
    }

    /** @return Collection<int, Category> */
    private function categories(?Product $product = null): Collection
    {
        $selected = $product?->category_id === null ? null : (int) $product->category_id;

        return Category::query()
            ->where('company_id', $this->companyId())
            ->where(function (Builder $query) use ($selected): void {
                $query->where('is_active', true);
                if ($selected !== null) {
                    $query->orWhere('id', $selected);
                }
            })
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Unit> */
    private function units(?Product $product = null): Collection
    {
        $selected = $product === null ? null : (int) $product->unit_id;

        return Unit::query()
            ->where('company_id', $this->companyId())
            ->where(function (Builder $query) use ($selected): void {
                $query->where('is_active', true);
                if ($selected !== null) {
                    $query->orWhere('id', $selected);
                }
            })
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Tax> */
    private function taxes(?Product $product = null): Collection
    {
        $selected = $product === null ? null : (int) $product->tax_id;

        return Tax::query()
            ->where('company_id', $this->companyId())
            ->where(function (Builder $query) use ($selected): void {
                $query->where('is_active', true);
                if ($selected !== null) {
                    $query->orWhere('id', $selected);
                }
            })
            ->orderBy('rate')
            ->orderBy('name')
            ->get();
    }

    /** @return list<string> */
    private function barcodeLines(mixed $value): array
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return [];
        }

        $lines = preg_split('/\R/u', $raw);
        if ($lines === false) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), $lines),
            static fn (string $line): bool => $line !== '',
        ));
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();
        if (! is_int($companyId)) {
            throw new LogicException('Product management requires a persisted active company.');
        }

        return $companyId;
    }
}
