<?php

namespace App\Actions\Imports;

use App\Models\Period\ImportCostAllocation;
use App\Models\Period\ImportCostItem;
use App\Models\Period\ImportFile;
use App\Support\Auth\MutationAuthorizer;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SaveImportCostItem
{
    /** @param array<string,mixed> $data */
    public function handle(ImportFile $file, array $data, ?ImportCostItem $item = null): ImportCostItem
    {
        MutationAuthorizer::authorize('import_shipments.update');

        return DB::connection('period')->transaction(function () use ($file, $data, $item): ImportCostItem {
            $lockedFile = ImportFile::query()->lockForUpdate()->findOrFail($file->id);

            if ($lockedFile->isLocked()) {
                throw new DomainException('Teslim alınmış ithalat dosyasında maliyet kalemi değiştirilemez.');
            }

            $model = $item
                ? ImportCostItem::query()->lockForUpdate()->findOrFail($item->id)
                : new ImportCostItem(['import_file_id' => $lockedFile->id]);

            if ($model->exists && (int) $model->import_file_id !== (int) $lockedFile->id) {
                throw new DomainException('Maliyet kalemi başka ithalat dosyasına ait.');
            }

            $basis = (string) ($data['allocation_basis'] ?? 'value');

            if (! in_array($basis, ['value', 'weight', 'volume'], true)) {
                throw new DomainException('Maliyet dağıtım esası değer/ağırlık/hacim olmalıdır.');
            }

            $amount = bcadd((string) $data['amount'], '0', 4);

            if (bccomp($amount, '0', 4) < 0) {
                throw new DomainException('İthalat maliyet tutarı negatif olamaz.');
            }

            $model->fill([
                'name' => trim((string) $data['name']),
                'amount' => $amount,
                'currency' => strtoupper(trim((string) $data['currency'])),
                'exchange_rate' => ($data['exchange_rate'] ?? null) ?: null,
                'allocation_basis' => $basis,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'amount_try' => null,
                'allocated_at' => null,
            ]);

            if ($model->name === '' || strlen($model->currency) !== 3) {
                throw new DomainException('Maliyet kalemi adı ve para birimi zorunludur.');
            }

            $model->save();

            ImportCostAllocation::query()
                ->where('import_cost_item_id', $model->id)
                ->delete();
            $lockedFile->packages()->update([
                'allocated_cost_try' => null,
                'landed_unit_cost_try' => null,
            ]);

            return $model->refresh();
        }, attempts: 3);
    }
}
