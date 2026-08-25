<?php

namespace App\Modules\Products\Actions;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Products\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class ManageCategory
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
    ) {}

    public function create(CatalogMasterData $data): Category
    {
        $companyId = $this->companyId();
        $code = $this->normalizeCode($data->code);
        $name = $this->normalizeName($data->name);
        $this->assertCodeAvailable($companyId, $code);

        try {
            return DB::transaction(function () use ($companyId, $code, $name, $data): Category {
                $category = Category::query()->create([
                    'company_id' => $companyId,
                    'code' => $code,
                    'name' => $name,
                    'is_active' => $data->isActive,
                ]);

                $this->audit->record(
                    AuditAction::CategoryCreated,
                    AuditTargetType::Category,
                    $category->getKey(),
                    after: $this->snapshot($category),
                );

                return $category;
            });
        } catch (QueryException $exception) {
            $this->throwCodeConflict($exception);
        }
    }

    public function update(int $categoryId, CatalogMasterData $data): Category
    {
        $companyId = $this->companyId();
        $code = $this->normalizeCode($data->code);
        $name = $this->normalizeName($data->name);

        try {
            return DB::transaction(function () use ($companyId, $categoryId, $code, $name, $data): Category {
                $category = Category::query()
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->findOrFail($categoryId);

                $this->assertCodeAvailable($companyId, $code, $categoryId);
                $before = $this->snapshot($category);

                $category->fill([
                    'code' => $code,
                    'name' => $name,
                    'is_active' => $data->isActive,
                ]);
                $category->save();

                $this->audit->record(
                    AuditAction::CategoryUpdated,
                    AuditTargetType::Category,
                    $category->getKey(),
                    before: $before,
                    after: $this->snapshot($category),
                );

                return $category;
            });
        } catch (QueryException $exception) {
            $this->throwCodeConflict($exception);
        }
    }

    private function normalizeCode(string $raw): string
    {
        $code = mb_strtoupper(trim($raw));
        if (preg_match('/^[A-Z0-9]+(?:[._-][A-Z0-9]+)*$/', $code) !== 1 || mb_strlen($code) > 64) {
            throw ValidationException::withMessages([
                'code' => 'Kategori kodu 1-64 karakter olmalı ve yalnız harf, rakam, nokta, alt çizgi veya tire içermelidir.',
            ]);
        }

        return $code;
    }

    private function normalizeName(string $raw): string
    {
        $name = trim($raw);
        if ($name === '' || mb_strlen($name) > 160) {
            throw ValidationException::withMessages(['name' => 'Kategori adı 1-160 karakter olmalıdır.']);
        }

        return $name;
    }

    private function assertCodeAvailable(int $companyId, string $code, ?int $ignoreId = null): void
    {
        $query = Category::query()
            ->where('company_id', $companyId)
            ->whereRaw('lower(code) = ?', [mb_strtolower($code)]);

        if ($ignoreId !== null) {
            $query->where('id', '<>', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['code' => 'Bu kategori kodu şirkette zaten kullanılıyor.']);
        }
    }

    private function throwCodeConflict(QueryException $exception): never
    {
        if ((string) $exception->getCode() !== '23505') {
            throw $exception;
        }

        throw ValidationException::withMessages(['code' => 'Bu kategori kodu şirkette zaten kullanılıyor.']);
    }

    /** @return array{code:string,name:string,is_active:bool} */
    private function snapshot(Category $category): array
    {
        return [
            'code' => (string) $category->code,
            'name' => (string) $category->name,
            'is_active' => (bool) $category->is_active,
        ];
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();
        if (! is_int($companyId)) {
            throw new LogicException('Category management requires a persisted active company.');
        }

        return $companyId;
    }
}
