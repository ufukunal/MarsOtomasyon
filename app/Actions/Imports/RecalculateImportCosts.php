<?php

namespace App\Actions\Imports;

use App\Models\Period\ImportFile;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Imports\ImportCostAllocator;
use DomainException;
use Illuminate\Support\Facades\DB;

final class RecalculateImportCosts
{
    public function __construct(private readonly ImportCostAllocator $allocator) {}

    public function handle(ImportFile $file, string $idempotencyKey): ImportFile
    {
        MutationAuthorizer::authorize('import_shipments.update');

        $id = IdempotencyKey::run(
            $idempotencyKey,
            'import.cost.allocate:'.$file->id,
            fn (): int => DB::connection('period')->transaction(function () use ($file): int {
                $locked = ImportFile::query()->lockForUpdate()->findOrFail($file->id);

                if ($locked->isLocked()) {
                    throw new DomainException('Teslim alınmış ithalat dosyasında maliyet yeniden dağıtılamaz.');
                }

                $this->allocator->recalculate($locked);

                return (int) $locked->id;
            }, attempts: 3),
        );

        return ImportFile::query()->with(['packages', 'costItems'])->findOrFail((int) $id);
    }
}
