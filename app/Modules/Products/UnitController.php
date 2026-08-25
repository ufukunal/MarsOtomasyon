<?php

namespace App\Modules\Products;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Products\Actions\CatalogMasterData;
use App\Modules\Products\Actions\ManageUnit;
use App\Modules\Products\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use LogicException;

final readonly class UnitController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private ManageUnit $units,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = $this->statusFilter((string) $request->query('status', 'all'));

        $query = Unit::query()
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

        return view('products.units.index', [
            'units' => $query->orderByDesc('is_active')->orderBy('name')->paginate(50)->withQueryString(),
            'search' => $search,
            'statusFilter' => $status,
        ]);
    }

    public function create(): View
    {
        return view('products.units.form', ['unit' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $unit = $this->units->create($this->data($validated));

        return redirect()->route('inventory.units.edit', $unit->getKey())
            ->with('status', 'Birim oluşturuldu.');
    }

    public function edit(int $unit): View
    {
        return view('products.units.form', [
            'unit' => $this->unit($unit),
        ]);
    }

    public function update(Request $request, int $unit): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $updated = $this->units->update($unit, $this->data($validated));

        return redirect()->route('inventory.units.edit', $updated->getKey())
            ->with('status', 'Birim güncellendi.');
    }

    /** @return array{code:list<string>,name:list<string>,status:list<string>} */
    private function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:80'],
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

    private function unit(int $id): Unit
    {
        return Unit::query()
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
            throw new LogicException('Unit screens require a persisted active company.');
        }

        return $companyId;
    }
}
