<?php

namespace App\Modules\Products;

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Products\Actions\UpdateProductSuppliers;
use App\Modules\Products\Enums\ProductFileKind;
use App\Modules\Products\Files\ProductFileManager;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ProductResourcesController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private UpdateProductSuppliers $updateSuppliers,
        private ProductFileManager $files,
    ) {}

    public function edit(int $product): View
    {
        $productModel = $this->product($product);
        $productModel->load('supplierRelations.account');
        $selectedSupplierIds = $productModel->supplierRelations
            ->pluck('account_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        return view('products.resources', [
            'product' => $productModel,
            'supplierAccounts' => $this->supplierAccounts(...$selectedSupplierIds),
            'selectedSupplierIds' => $selectedSupplierIds,
            'productFiles' => $this->files->all($product),
            'fileKinds' => ProductFileKind::cases(),
        ]);
    }

    public function updateSuppliers(Request $request, int $product): RedirectResponse
    {
        $this->product($product);
        $validated = $request->validate([
            'supplier_ids' => ['nullable', 'array', 'max:100'],
            'supplier_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);
        $supplierIds = array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            is_array($validated['supplier_ids'] ?? null) ? $validated['supplier_ids'] : [],
        ));

        $this->updateSuppliers->handle($product, $supplierIds);

        return redirect()->route('inventory.products.resources.edit', $product)
            ->with('status', 'Ürün tedarikçi ilişkileri güncellendi.');
    }

    public function uploadFile(Request $request, int $product): RedirectResponse
    {
        $this->product($product);
        $validated = $request->validate([
            'kind' => ['required', Rule::enum(ProductFileKind::class)],
            'file' => ['required', 'file', 'max:51200'],
            'label' => ['nullable', 'string', 'max:160'],
        ]);
        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422, 'Dosya yükleme isteği geçersiz.');
        }

        $this->files->upload(
            $product,
            ProductFileKind::from((string) $validated['kind']),
            $upload,
            isset($validated['label']) ? (string) $validated['label'] : null,
        );

        return redirect()->route('inventory.products.resources.edit', $product)
            ->with('status', 'Ürün dosyası private storage alanına yüklendi.');
    }

    public function downloadFile(int $product, int $file): StreamedResponse
    {
        return $this->files->download($product, $file);
    }

    public function detachFile(int $product, int $file): RedirectResponse
    {
        $this->files->detach($product, $file);

        return redirect()->route('inventory.products.resources.edit', $product)
            ->with('status', 'Ürün dosya bağlantısı kaldırıldı. Orijinal dosya arşivde korunuyor.');
    }

    private function product(int $id): Product
    {
        return Product::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);
    }

    /** @return Collection<int, Account> */
    private function supplierAccounts(int ...$selectedIds): Collection
    {
        return Account::query()
            ->where('company_id', $this->companyId())
            ->whereIn('type', [AccountType::Supplier->value, AccountType::Mixed->value])
            ->where(function (Builder $query) use ($selectedIds): void {
                $query->where('status', AccountStatus::Active->value);
                if ($selectedIds !== []) {
                    $query->orWhereIn('id', $selectedIds);
                }
            })
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('legal_name')
            ->orderBy('code')
            ->get();
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();

        return is_int($id)
            ? $id
            : throw new LogicException('Product resources management requires a persisted active company.');
    }
}
