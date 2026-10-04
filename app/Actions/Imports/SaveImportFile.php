<?php

namespace App\Actions\Imports;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Models\Period\ImportCostAllocation;
use App\Models\Period\ImportFile;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SaveImportFile
{
    public function __construct(private readonly GenerateDocumentNumber $numbers) {}

    /** @param array<string,mixed> $data */
    public function handle(array $data, ?ImportFile $file = null, ?int $expectedVersion = null): ImportFile
    {
        MutationAuthorizer::authorize('import_shipments.'.($file ? 'update' : 'create'));

        return DB::connection('period')->transaction(function () use ($data, $file, $expectedVersion): ImportFile {
            $locked = $file
                ? ImportFile::query()->lockForUpdate()->findOrFail($file->id)
                : new ImportFile();

            if ($locked->exists && $locked->isLocked()) {
                throw new DomainException('Teslim alınmış/kapanmış ithalat dosyası değiştirilemez.');
            }

            if ($locked->exists && $expectedVersion !== null && (int) $locked->version !== $expectedVersion) {
                throw new DomainException('İthalat dosyası başka bir kullanıcı tarafından güncellendi.');
            }

            $currency = strtoupper(trim((string) ($data['currency'] ?? '')));
            $rate = $data['exchange_rate'] ?? null;

            if (strlen($currency) !== 3) {
                throw new DomainException('İthalat para birimi 3 karakter olmalıdır.');
            }

            if ($currency === 'TRY') {
                throw new DomainException('İthalat dosyası dövizli olmalıdır.');
            }

            if ($rate !== null && bccomp((string) $rate, '0', 6) <= 0) {
                throw new DomainException('İthalat kuru pozitif olmalıdır.');
            }

            $oldRate = $locked->exists ? (string) ($locked->exchange_rate ?? '') : null;

            $locked->fill([
                'supplier_contact_id' => (int) $data['supplier_contact_id'],
                'receiving_location_id' => ($data['receiving_location_id'] ?? null) ? (int) $data['receiving_location_id'] : null,
                'country' => trim((string) ($data['country'] ?? '')) ?: null,
                'incoterm' => trim((string) ($data['incoterm'] ?? '')) ?: null,
                'currency' => $currency,
                'exchange_rate' => $rate !== null ? bcadd((string) $rate, '0', 6) : null,
                'etd' => $data['etd'] ?? null,
                'eta' => $data['eta'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ]);

            if (! in_array($locked->status, ['draft', 'in_transit', 'customs'], true)) {
                throw new DomainException('Düzenlenebilir ithalat dosyası durumu geçersiz.');
            }

            if (! $locked->exists) {
                $actor = auth()->user();
                $locked->number = $this->numbers->handle('import_file');
                $locked->created_by = $actor?->id;
                $locked->created_by_name = $actor?->name;
            } else {
                $locked->version = (int) $locked->version + 1;
            }

            $locked->save();

            if ($locked->exists && $oldRate !== null && $oldRate !== (string) $locked->exchange_rate) {
                $itemIds = $locked->costItems()->pluck('id');
                ImportCostAllocation::query()->whereIn('import_cost_item_id', $itemIds)->delete();
                $locked->costItems()->update(['amount_try' => null, 'allocated_at' => null]);
                $locked->packages()->update([
                    'goods_value_try' => null,
                    'allocated_cost_try' => null,
                    'landed_unit_cost_try' => null,
                ]);
            }

            AuditContext::period(
                'İthalat dosyası kaydedildi.',
                ['import_file_id' => $locked->id, 'number' => $locked->number, 'status' => $locked->status],
                $locked,
                'import_file_saved',
            );

            return $locked->refresh();
        }, attempts: 3);
    }
}
