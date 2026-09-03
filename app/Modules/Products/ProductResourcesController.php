<?php

namespace App\Modules\Products;

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Products\Actions\UpdateProductSuppliers;
use App\Modules\Products\Enums\ProductFileKind;
use App\Modules\Products\Files\ProductFileManager;
use App\Modules\Products\Files\ProductImageOperations;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ProductResourcesController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private UpdateProductSuppliers $updateSuppliers,
        private ProductFileManager $files,
        private ProductImageOperations $images,
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
            'mediaTargetProducts' => $this->mediaTargetProducts($productModel),
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

        return $this->resourcesRedirect($product, 'Ürün tedarikçi ilişkileri güncellendi.');
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

        return $this->resourcesRedirect($product, 'Ürün dosyası private storage alanına yüklendi.');
    }

    public function downloadFile(int $product, int $file): StreamedResponse
    {
        return $this->files->download($product, $file);
    }

    public function detachFile(int $product, int $file): RedirectResponse
    {
        $this->files->detach($product, $file);

        return $this->resourcesRedirect($product, 'Ürün dosya bağlantısı kaldırıldı. Orijinal dosya arşivde korunuyor.');
    }

    public function setMainMedia(int $product, int $file): RedirectResponse
    {
        $this->images->setMain($product, $file);

        return $this->resourcesRedirect($product, 'Ana ürün görseli güncellendi.');
    }

    public function reorderMedia(Request $request, int $product): RedirectResponse
    {
        $this->product($product);
        $validated = $request->validate([
            'positions' => ['required', 'array', 'min:1', 'max:32768'],
            'positions.*' => ['required', 'integer', 'min:0', 'max:32767'],
        ]);
        $raw = is_array($validated['positions'] ?? null) ? $validated['positions'] : [];
        $positions = [];
        foreach ($raw as $fileId => $position) {
            if (! is_numeric($fileId)) {
                throw ValidationException::withMessages(['positions' => 'Medya dosya kimliği geçersiz.']);
            }
            $positions[(int) $fileId] = (int) $position;
        }
        if (count($positions) !== count(array_unique(array_values($positions)))) {
            throw ValidationException::withMessages(['positions' => 'Her medya görselinin sırası benzersiz olmalıdır.']);
        }
        asort($positions, SORT_NUMERIC);

        $this->images->reorder($product, array_keys($positions));

        return $this->resourcesRedirect($product, 'Galeri sırası güncellendi.');
    }

    public function updateMediaDestinations(Request $request, int $product, int $file): RedirectResponse
    {
        $validated = $request->validate([
            'destinations' => ['nullable', 'string', 'max:4096'],
        ]);
        $raw = trim((string) ($validated['destinations'] ?? ''));
        $destinations = $raw === ''
            ? []
            : preg_split('/[\s,;]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($destinations)) {
            $destinations = [];
        }

        $this->images->updateDestinations($product, $file, $destinations);

        return $this->resourcesRedirect($product, 'Görsel hedef kümeleri güncellendi.');
    }

    public function updateMediaTransform(Request $request, int $product, int $file): RedirectResponse
    {
        $validated = $request->validate([
            'crop_x' => ['nullable', 'integer', 'min:0', 'max:100000', 'required_with:crop_y,crop_width,crop_height'],
            'crop_y' => ['nullable', 'integer', 'min:0', 'max:100000', 'required_with:crop_x,crop_width,crop_height'],
            'crop_width' => ['nullable', 'integer', 'min:1', 'max:100000', 'required_with:crop_x,crop_y,crop_height'],
            'crop_height' => ['nullable', 'integer', 'min:1', 'max:100000', 'required_with:crop_x,crop_y,crop_width'],
            'rotate' => ['nullable', 'integer', Rule::in([0, 90, 180, 270])],
            'flip_horizontal' => ['nullable', 'boolean'],
            'flip_vertical' => ['nullable', 'boolean'],
            'resize_width' => ['nullable', 'integer', 'min:1', 'max:100000', 'required_with:resize_height'],
            'resize_height' => ['nullable', 'integer', 'min:1', 'max:100000', 'required_with:resize_width'],
            'resize_mode' => ['nullable', Rule::in(['contain', 'cover', 'stretch'])],
        ]);

        $metadata = [];
        if (isset($validated['crop_x'], $validated['crop_y'], $validated['crop_width'], $validated['crop_height'])) {
            $metadata['crop'] = [
                'x' => (int) $validated['crop_x'],
                'y' => (int) $validated['crop_y'],
                'width' => (int) $validated['crop_width'],
                'height' => (int) $validated['crop_height'],
            ];
        }
        if (array_key_exists('rotate', $validated) && $validated['rotate'] !== null) {
            $metadata['rotate'] = (int) $validated['rotate'];
        }
        if ($request->hasAny(['flip_horizontal', 'flip_vertical', 'flip_present'])) {
            $metadata['flip'] = [
                'horizontal' => $request->boolean('flip_horizontal'),
                'vertical' => $request->boolean('flip_vertical'),
            ];
        }
        if (isset($validated['resize_width'], $validated['resize_height'])) {
            $metadata['resize'] = [
                'width' => (int) $validated['resize_width'],
                'height' => (int) $validated['resize_height'],
                'mode' => (string) ($validated['resize_mode'] ?? 'contain'),
            ];
        }

        $this->images->updateTransformMetadata($product, $file, $metadata);

        return $this->resourcesRedirect($product, 'Tahribatsız görsel dönüşüm reçetesi güncellendi.');
    }

    public function updateMediaProviderValidation(Request $request, int $product, int $file): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:80'],
            'status' => ['required', Rule::in(['pending', 'valid', 'warning', 'invalid'])],
            'messages' => ['nullable', 'string', 'max:10000'],
        ]);
        $messages = preg_split('/\r\n|\r|\n/u', trim((string) ($validated['messages'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($messages)) {
            $messages = [];
        }

        $this->images->recordProviderValidation(
            $product,
            $file,
            (string) $validated['provider'],
            (string) $validated['status'],
            $messages,
        );

        return $this->resourcesRedirect($product, 'Provider görsel doğrulama metadata bilgisi güncellendi.');
    }

    public function copyMedia(Request $request, int $product, int $file): RedirectResponse
    {
        $validated = $request->validate([
            'target_product_id' => ['required', 'integer', 'min:1'],
        ]);
        $this->images->copy($product, $file, (int) $validated['target_product_id']);

        return $this->resourcesRedirect($product, 'Görsel hedef ürüne kopyalandı; aynı private dosya varlığı yeniden kullanıldı.');
    }

    public function moveMedia(Request $request, int $product, int $file): RedirectResponse
    {
        $validated = $request->validate([
            'target_product_id' => ['required', 'integer', 'min:1'],
        ]);
        $this->images->move($product, $file, (int) $validated['target_product_id']);

        return $this->resourcesRedirect($product, 'Görsel hedef ürüne taşındı.');
    }

    public function quarantineMedia(Request $request, int $product, int $file): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $this->images->quarantine($product, $file, (string) $validated['reason']);

        return $this->resourcesRedirect($product, 'Görsel karantinaya alındı ve kullanım/dosya indirme akışlarından kapatıldı.');
    }

    public function releaseMediaQuarantine(int $product, int $file): RedirectResponse
    {
        $this->images->releaseQuarantine($product, $file);

        return $this->resourcesRedirect($product, 'Görsel karantinadan çıkarıldı.');
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

    /** @return Collection<int, Product> */
    private function mediaTargetProducts(Product $source): Collection
    {
        return Product::query()
            ->where('company_id', $this->companyId())
            ->whereKeyNot($source->getKey())
            ->orderBy('code')
            ->orderBy('name')
            ->limit(250)
            ->get(['id', 'code', 'name']);
    }

    private function resourcesRedirect(int $product, string $status): RedirectResponse
    {
        return redirect()->route('inventory.products.resources.edit', $product)->with('status', $status);
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();

        return is_int($id)
            ? $id
            : throw new LogicException('Product resources management requires a persisted active company.');
    }
}
