<?php

namespace App\Modules\Products;

use App\Foundation\Features\FeatureKey;
use App\Foundation\Features\FeatureRegistry;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Products\Files\ProductFamilyMediaManager;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductFamily;
use App\Modules\Products\Variants\ProductFamilyChannelService;
use App\Modules\Products\Variants\ProductVariantService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use LogicException;

final readonly class ProductFamilyController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private FeatureRegistry $features,
        private ProductVariantService $variants,
        private ProductFamilyChannelService $channels,
        private ProductFamilyMediaManager $media,
    ) {}

    public function index(): View
    {
        $this->assertFeature();

        return view('product-families.index');
    }

    public function data(Request $request): JsonResponse
    {
        $this->assertFeature();
        $draw = max(0, (int) $request->query('draw', 0));
        $start = max(0, (int) $request->query('start', 0));
        $length = min(100, max(10, (int) $request->query('length', 25)));
        $type = (string) $request->query('type', 'all');
        if (! in_array($type, ['all', 'family', 'variant', 'simple'], true)) {
            $type = 'all';
        }
        $search = trim((string) $request->input('search.value', $request->query('q', '')));
        $companyId = $this->companyId();

        $families = DB::table('product_families as pf')
            ->where('pf.company_id', $companyId)
            ->selectRaw("'family' as row_type, pf.id as row_id, pf.code, pf.name, pf.id as family_id, NULL as product_id");
        $products = DB::table('products as p')
            ->leftJoin('product_variant_relations as pvr', function ($join): void {
                $join->on('pvr.company_id', '=', 'p.company_id')->on('pvr.product_id', '=', 'p.id');
            })
            ->where('p.company_id', $companyId)
            ->selectRaw("CASE WHEN pvr.id IS NULL THEN 'simple' ELSE 'variant' END as row_type, p.id as row_id, p.code, p.name, pvr.product_family_id as family_id, p.id as product_id");

        $catalog = DB::query()->fromSub($families->unionAll($products), 'catalog');
        $recordsTotal = (clone $catalog)->count();
        if ($type !== 'all') {
            $catalog->where('row_type', $type);
        }
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $catalog->where(function ($query) use ($needle): void {
                $query->whereRaw('lower(code) LIKE ?', [$needle])->orWhereRaw('lower(name) LIKE ?', [$needle]);
            });
        }
        $recordsFiltered = (clone $catalog)->count();
        $rows = $catalog
            ->orderBy('row_type')
            ->orderBy('name')
            ->orderBy('row_id')
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(function (object $row): array {
                $familyId = is_numeric($row->family_id ?? null) ? (int) $row->family_id : null;
                $route = (string) $row->row_type === 'family' && $familyId !== null
                    ? route('inventory.product-families.show', $familyId)
                    : ($familyId !== null ? route('inventory.product-families.show', $familyId) : route('inventory.products.show', (int) $row->product_id));

                return [
                    'type' => (string) $row->row_type,
                    'id' => (int) $row->row_id,
                    'code' => (string) $row->code,
                    'name' => (string) $row->name,
                    'family_id' => $familyId,
                    'url' => $route,
                ];
            })->all();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ]);
    }

    public function create(): View
    {
        $this->assertFeature();

        return view('product-families.form', ['family' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertFeature();
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:191'],
            'shared_content' => ['nullable', 'string', 'max:20000'],
        ]);
        $family = $this->domain(fn () => $this->variants->createFamily(
            $this->companyId(),
            (string) $validated['code'],
            (string) $validated['name'],
            $this->jsonContent($validated['shared_content'] ?? null),
        ));

        return redirect()->route('inventory.product-families.show', $family->getKey())->with('status', 'Ürün ailesi oluşturuldu.');
    }

    public function show(int $family): View
    {
        $this->assertFeature();
        $model = $this->family($family);
        $model->load(['dimensions.values', 'variants.product', 'variants.assignments.value', 'channelMappings']);
        $simpleProducts = Product::query()
            ->where('company_id', $this->companyId())
            ->whereDoesntHave('variantRelation')
            ->orderBy('name')
            ->limit(250)
            ->get(['id', 'code', 'name']);
        $connections = DB::table('integration_connections')
            ->where('company_id', $this->companyId())
            ->where('status', 'active')
            ->orderBy('provider')
            ->orderBy('name')
            ->get(['id', 'provider', 'name']);

        return view('product-families.show', [
            'family' => $model,
            'simpleProducts' => $simpleProducts,
            'connections' => $connections,
            'media' => $this->media->all($family),
            'hero' => $this->media->hero($family),
        ]);
    }

    public function edit(int $family): View
    {
        $this->assertFeature();

        return view('product-families.form', ['family' => $this->family($family)]);
    }

    public function update(Request $request, int $family): RedirectResponse
    {
        $this->assertFeature();
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:191'],
            'shared_content' => ['nullable', 'string', 'max:20000'],
        ]);
        $this->domain(fn () => $this->variants->updateFamily(
            $this->companyId(), $family, (string) $validated['code'], (string) $validated['name'], $this->jsonContent($validated['shared_content'] ?? null),
        ));

        return redirect()->route('inventory.product-families.show', $family)->with('status', 'Ürün ailesi güncellendi.');
    }

    public function destroy(int $family): RedirectResponse
    {
        $this->assertFeature();
        $this->domain(fn () => $this->variants->deleteFamily($this->companyId(), $family));

        return redirect()->route('inventory.product-families.index')->with('status', 'Ürün ailesi silindi; SKU ürünleri korundu.');
    }

    public function storeDimension(Request $request, int $family): RedirectResponse
    {
        $this->assertFeature();
        $validated = $request->validate(['code' => ['required', 'string', 'max:64'], 'name' => ['required', 'string', 'max:120'], 'position' => ['nullable', 'integer', 'min:0', 'max:32767']]);
        $this->domain(fn () => $this->variants->addDimension($this->companyId(), $family, (string) $validated['code'], (string) $validated['name'], (int) ($validated['position'] ?? 0)));

        return back()->with('status', 'Varyant boyutu eklendi.');
    }

    public function storeValue(Request $request, int $family, int $dimension): RedirectResponse
    {
        $this->assertFeature();
        $validated = $request->validate(['code' => ['required', 'string', 'max:64'], 'label' => ['required', 'string', 'max:120'], 'position' => ['nullable', 'integer', 'min:0', 'max:32767']]);
        $this->domain(fn () => $this->variants->addValue($this->companyId(), $family, $dimension, (string) $validated['code'], (string) $validated['label'], (int) ($validated['position'] ?? 0)));

        return back()->with('status', 'Varyant değeri eklendi.');
    }

    public function assignVariant(Request $request, int $family): RedirectResponse
    {
        $this->assertFeature();
        $validated = $request->validate(['product_id' => ['required', 'integer', 'min:1'], 'dimension_values' => ['required', 'array', 'min:1'], 'dimension_values.*' => ['required', 'integer', 'min:1']]);
        $pairs = [];
        foreach ((array) $validated['dimension_values'] as $dimensionId => $valueId) {
            $dimensionKey = (string) $dimensionId;
            if (! ctype_digit($dimensionKey) || (int) $dimensionKey < 1) {
                throw ValidationException::withMessages(['dimension_values' => 'Boyut kimliği geçersiz.']);
            }
            $pairs[(int) $dimensionKey] = (int) $valueId;
        }
        $this->domain(fn () => $this->variants->assignProduct($this->companyId(), $family, (int) $validated['product_id'], $pairs));

        return back()->with('status', 'SKU varyant olarak aileye bağlandı.');
    }

    public function mapChannel(Request $request, int $family): RedirectResponse
    {
        $this->assertFeature();
        $validated = $request->validate(['connection_id' => ['required', 'integer', 'min:1'], 'provider' => ['required', 'string', 'max:64'], 'external_parent_id' => ['required', 'string', 'max:192']]);
        $this->domain(fn () => $this->channels->mapParent($this->companyId(), (int) $validated['connection_id'], $family, (string) $validated['provider'], (string) $validated['external_parent_id']));

        return back()->with('status', 'Marketplace parent mapping kaydedildi.');
    }

    public function linkMedia(Request $request, int $family): RedirectResponse
    {
        $this->assertFeature();
        $validated = $request->validate(['file_asset_id' => ['required', 'integer', 'min:1'], 'label' => ['nullable', 'string', 'max:160']]);
        try {
            $this->media->linkExistingAsset($family, (int) $validated['file_asset_id'], isset($validated['label']) ? (string) $validated['label'] : null);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['file_asset_id' => $exception->getMessage()]);
        }

        return back()->with('status', 'Aile medyası bağlandı.');
    }

    public function setHero(int $family, int $attachment): RedirectResponse
    {
        $this->assertFeature();
        $this->media->setHero($family, $attachment);

        return back()->with('status', 'Aile kapak görseli seçildi.');
    }

    public function detachMedia(int $family, int $attachment): RedirectResponse
    {
        $this->assertFeature();
        $this->media->detach($family, $attachment);

        return back()->with('status', 'Aile medyası kaldırıldı.');
    }

    private function assertFeature(): void
    {
        abort_unless($this->features->enabled(FeatureKey::ProductFamilyVariant), 404);
    }

    private function family(int $id): ProductFamily
    {
        return ProductFamily::query()->where('company_id', $this->companyId())->findOrFail($id);
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();

        return is_int($id) ? $id : throw new LogicException('Product family operation requires a persisted company.');
    }

    /** @return array<string,mixed>|null */
    private function jsonContent(mixed $value): ?array
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['shared_content' => 'Paylaşılan içerik geçerli bir JSON nesnesi olmalıdır.']);
        }

        return $decoded;
    }

    private function domain(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['family' => $exception->getMessage()]);
        }
    }
}
