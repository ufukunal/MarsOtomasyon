<?php

namespace App\Modules\Products;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Products\Actions\CatalogMasterData;
use App\Modules\Products\Actions\ManageCategory;
use App\Modules\Products\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use LogicException;

final readonly class CategoryController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private ManageCategory $categories,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = $this->statusFilter((string) $request->query('status', 'all'));

        $query = Category::query()
            ->where('company_id', $this->companyId())
            ->withCount('products');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->whereRaw('code ILIKE ?', [$like])
                    ->orWhereRaw('name ILIKE ?', [$like]);
            });
        }

        if ($status !== 'all') {
            $query->where('is_active', $status === 'active');
        }

        return view('products.categories.index', [
            'categories' => $query->orderByDesc('is_active')->orderBy('name')->paginate(50)->withQueryString(),
            'search' => $search,
            'statusFilter' => $status,
        ]);
    }

    public function create(): View
    {
        return view('products.categories.form', ['category' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $category = $this->categories->create($this->data($validated));

        return redirect()->route('inventory.categories.edit', $category->getKey())
            ->with('status', 'Kategori oluşturuldu.');
    }

    public function edit(int $category): View
    {
        return view('products.categories.form', [
            'category' => $this->category($category),
        ]);
    }

    public function update(Request $request, int $category): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $updated = $this->categories->update($category, $this->data($validated));

        return redirect()->route('inventory.categories.edit', $updated->getKey())
            ->with('status', 'Kategori güncellendi.');
    }

    /** @return array{code:list<string>,name:list<string>,status:list<string>} */
    private function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:160'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    /** @param array<string, mixed> $validated */
    private function data(array $validated): CatalogMasterData
    {
        return new CatalogMasterData(
            code: (string) $validated['code'],
            name: (string) $validated['name'],
            isActive: (string) $validated['status'] === 'active',
        );
    }

    private function category(int $id): Category
    {
        return Category::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);
    }

    private function statusFilter(string $status): string
    {
        return in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all';
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();
        if (! is_int($companyId)) {
            throw new LogicException('Category screens require a persisted active company.');
        }

        return $companyId;
    }
}
