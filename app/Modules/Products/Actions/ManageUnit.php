<?php

namespace App\Modules\Products\Actions;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Products\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class ManageUnit
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
    ) {}

    public function create(CatalogMasterData $data): Unit
    {
        $companyId = $this->companyId();
        $code = $this->normalizeCode($data->code);
        $name = $this->normalizeName($data->name);
        $this->assertCodeAvailable($companyId, $code);

        try {
            return DB::transaction(function () use ($companyId, $code, $name, $data): Unit {
                $unit = Unit::query()->create([
                    'company_id' => $companyId,
                    'code' => $code,
                    'name' => $name,
                    'is_active' => $data->isActive,
                ]);

                $this->audit->record(
                    AuditAction::UnitCreated,
                    AuditTargetType::Unit,
                    $unit->getKey(),
                    after: $this->snapshot($unit),
                );

                return $unit;
            });
        } catch (QueryException $exception) {
            $this->throwCodeConflict($exception);
        }
    }

    public function update(int $unitId, CatalogMasterData $data): Unit
    {
        $companyId = $this->companyId();
        $code = $this->normalizeCode($data->code);
        $name = $this->normalizeName($data->name);

        try {
            return DB::transaction(function () use ($companyId, $unitId, $code, $name, $data): Unit {
                $unit = Unit::query()
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->findOrFail($unitId);

                $this->assertCodeAvailable($companyId, $code, $unitId);
                $before = $this->snapshot($unit);

                $unit->fill([
                    'code' => $code,
                    'name' => $name,
                    'is_active' => $data->isActive,
                ]);
                $unit->save();

                $this->audit->record(
                    AuditAction::UnitUpdated,
                    AuditTargetType::Unit,
                    $unit->getKey(),
                    before: $before,
                    after: $this->snapshot($unit),
                );

                return $unit;
            });
        } catch (QueryException $exception) {
            $this->throwCodeConflict($exception);
        }
    }

    private function normalizeCode(string $raw): string
    {
        $code = mb_strtoupper(trim($raw));
        if (preg_match('/^[A-Z0-9]+(?:[._-][A-Z0-9]+)*$/', $code) !== 1 || mb_strlen($code) > 32) {
            throw ValidationException::withMessages([
                'code' => 'Birim kodu 1-32 karakter olmalı ve yalnız harf, rakam, nokta, alt çizgi veya tire içermelidir.',
            ]);
        }

        return $code;
    }

    private function normalizeName(string $raw): string
    {
        $name = trim($raw);
        if ($name === '' || mb_strlen($name) > 80) {
            throw ValidationException::withMessages(['name' => 'Birim adı 1-80 karakter olmalıdır.']);
        }

        return $name;
    }

    private function assertCodeAvailable(int $companyId, string $code, ?int $ignoreId = null): void
    {
        $query = Unit::query()
            ->where('company_id', $companyId)
            ->whereRaw('lower(code) = ?', [mb_strtolower($code)]);

        if ($ignoreId !== null) {
            $query->where('id', '<>', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['code' => 'Bu birim kodu şirkette zaten kullanılıyor.']);
        }
    }

    private function throwCodeConflict(QueryException $exception): never
    {
        if ((string) $exception->getCode() !== '23505') {
            throw $exception;
        }

        throw ValidationException::withMessages(['code' => 'Bu birim kodu şirkette zaten kullanılıyor.']);
    }

    /** @return array{code:string,name:string,is_active:bool} */
    private function snapshot(Unit $unit): array
    {
        return [
            'code' => (string) $unit->code,
            'name' => (string) $unit->name,
            'is_active' => (bool) $unit->is_active,
        ];
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();
        if (! is_int($companyId)) {
            throw new LogicException('Unit management requires a persisted active company.');
        }

        return $companyId;
    }
}
