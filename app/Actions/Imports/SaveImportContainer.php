<?php

namespace App\Actions\Imports;

use App\Models\Period\ImportContainer;
use App\Models\Period\ImportFile;
use App\Support\Auth\MutationAuthorizer;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SaveImportContainer
{
    /** @param array<string,mixed> $data */
    public function handle(ImportFile $file, array $data, ?ImportContainer $container = null): ImportContainer
    {
        MutationAuthorizer::authorize('import_shipments.update');

        return DB::connection('period')->transaction(function () use ($file, $data, $container): ImportContainer {
            $lockedFile = ImportFile::query()->lockForUpdate()->findOrFail($file->id);

            if ($lockedFile->isLocked()) {
                throw new DomainException('Teslim alınmış ithalat dosyasında konteyner değiştirilemez.');
            }

            $model = $container
                ? ImportContainer::query()->lockForUpdate()->findOrFail($container->id)
                : new ImportContainer(['import_file_id' => $lockedFile->id]);

            if ($model->exists && (int) $model->import_file_id !== (int) $lockedFile->id) {
                throw new DomainException('Konteyner başka ithalat dosyasına ait.');
            }

            $model->fill([
                'container_no' => trim((string) $data['container_no']),
                'container_type' => trim((string) ($data['container_type'] ?? '')) ?: null,
                'seal_no' => trim((string) ($data['seal_no'] ?? '')) ?: null,
                'gross_weight_kg' => ($data['gross_weight_kg'] ?? null) ?: null,
                'volume_cbm' => ($data['volume_cbm'] ?? null) ?: null,
                'etd' => $data['etd'] ?? null,
                'eta' => $data['eta'] ?? null,
                'status' => $data['status'] ?? 'planned',
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ]);

            if ($model->container_no === '') {
                throw new DomainException('Konteyner numarası zorunludur.');
            }

            $model->save();

            return $model->refresh();
        }, attempts: 3);
    }
}
