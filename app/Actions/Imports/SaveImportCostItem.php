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

            $currency = strtoupper(trim((string) $data['currency']));

            if (strlen($currency) !== 3) {
                throw new DomainException('Maliyet kalemi para birimi 3 karakter olmalıdır.');
            }

            $exchangeRate = ($data['exchange_rate'] ?? null);
            $exchangeRate = $exchangeRate === null || trim((string) $exchangeRate) === ''
                ? null
                : bcadd((string) $exchangeRate, '0', 6);

            if ($currency === 'TRY') {
                $exchangeRate = '1.000000';
            } elseif ($currency === (string) $lockedFile->currency) {
                $exchangeRate = null;
            } elseif ($exchangeRate === null || bccomp($exchangeRate, '0', 6) <= 0) {
                throw new DomainException("{$currency}/TRY kuru bu maliyet kalemi için zorunludur.");
            }

            $model->fill([
                'name' => trim((string) $data['name']),
                'amount' => $amount,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'allocation_basis' => $basis,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'amount_try' => null,
                'allocated_at' => null,
            ]);

            if ($model->name === '') {
                throw new DomainException('Maliyet kalemi adı zorunludur.');
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
