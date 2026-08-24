<?php

namespace App\Modules\Core\Management;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Models\Branch;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final readonly class BranchManagementController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
    ) {}

    public function index(Request $request): View
    {
        return view('settings.branches.index', [
            'branches' => Branch::query()
                ->where('company_id', $this->companyId())
                ->orderByDesc('is_active')
                ->orderBy('code')
                ->get(),
            'activeBranchId' => $this->sessionBranchId($request),
        ]);
    }

    public function create(): View
    {
        return view('settings.branches.form', ['branch' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->assertCodeAvailable($data['code']);

        try {
            $branch = DB::transaction(function () use ($data): Branch {
                $branch = Branch::query()->create([
                    'company_id' => $this->companyId(),
                    ...$data,
                ]);

                $branchId = $this->branchKey($branch);
                $this->audit->record(
                    AuditAction::BranchCreated,
                    AuditTargetType::Branch,
                    $branchId,
                    after: $this->snapshot($branch),
                );

                return $branch;
            });
        } catch (QueryException $exception) {
            $this->throwDuplicateCode($exception);
        }

        return redirect()->route('settings.branches.show', $branch->getKey())
            ->with('status', 'Şube oluşturuldu.');
    }

    public function show(Request $request, int $branch): View
    {
        return view('settings.branches.show', [
            'branch' => $this->branch($branch),
            'activeBranchId' => $this->sessionBranchId($request),
        ]);
    }

    public function edit(int $branch): View
    {
        return view('settings.branches.form', ['branch' => $this->branch($branch)]);
    }

    public function update(Request $request, int $branch): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $updated = DB::transaction(function () use ($branch, $data): Branch {
                $locked = Branch::query()
                    ->where('company_id', $this->companyId())
                    ->lockForUpdate()
                    ->findOrFail($branch);

                $this->assertCodeAvailable($data['code'], $branch);
                $before = $this->snapshot($locked);

                $locked->fill($data);
                $locked->save();

                $this->audit->record(
                    AuditAction::BranchUpdated,
                    AuditTargetType::Branch,
                    $this->branchKey($locked),
                    before: $before,
                    after: $this->snapshot($locked),
                );

                return $locked;
            });
        } catch (QueryException $exception) {
            $this->throwDuplicateCode($exception);
        }

        $updatedId = $this->branchKey($updated);
        if (! $updated->is_active && $this->sessionBranchId($request) === $updatedId) {
            $request->session()->forget('active_branch_id');
        }

        return redirect()->route('settings.branches.show', $updatedId)
            ->with('status', 'Şube güncellendi.');
    }

    public function select(Request $request, int $branch): RedirectResponse
    {
        $selected = Branch::query()
            ->where('company_id', $this->companyId())
            ->where('is_active', true)
            ->findOrFail($branch);

        $request->session()->put('active_branch_id', $this->branchKey($selected));

        return redirect()->route('settings.branches.show', $selected->getKey())
            ->with('status', 'Aktif şube değiştirildi.');
    }

    /** @return array{code:string,name:string,is_active:bool} */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:160'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'code' => mb_strtoupper(trim((string) $validated['code'])),
            'name' => trim((string) $validated['name']),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function branch(int $id): Branch
    {
        return Branch::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);
    }

    private function assertCodeAvailable(string $code, ?int $exceptId = null): void
    {
        $query = Branch::query()
            ->where('company_id', $this->companyId())
            ->whereRaw('lower(code) = ?', [mb_strtolower($code)]);

        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['code' => 'Bu şube kodu zaten kullanılıyor.']);
        }
    }

    private function throwDuplicateCode(QueryException $exception): never
    {
        if ($exception->getCode() === '23505') {
            throw ValidationException::withMessages(['code' => 'Bu şube kodu zaten kullanılıyor.']);
        }

        throw $exception;
    }

    /** @return array{code:string,name:string,is_active:bool} */
    private function snapshot(Branch $branch): array
    {
        return [
            'code' => (string) $branch->code,
            'name' => (string) $branch->name,
            'is_active' => (bool) $branch->is_active,
        ];
    }

    private function branchKey(Branch $branch): int
    {
        $id = $branch->getKey();

        if (! is_int($id)) {
            throw new \LogicException('Branch persistence did not return an integer key.');
        }

        return $id;
    }

    private function sessionBranchId(Request $request): ?int
    {
        $value = $request->session()->get('active_branch_id');

        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();

        if (! is_int($companyId)) {
            throw new \LogicException('Branch management requires a persisted active company.');
        }

        return $companyId;
    }
}
