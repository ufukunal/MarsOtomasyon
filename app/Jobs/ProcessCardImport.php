<?php

namespace App\Jobs;

use App\Actions\Stock\ImportOpeningStock;
use App\Models\Period\CardImportBatch;
use App\Models\Period\CardImportError;
use App\Models\User;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Auth\PeriodPermissionContext;
use App\Support\Import\ImportFileReader;
use App\Support\Import\ImportMapping;
use App\Support\Import\ImportRowImporterResolver;
use App\Support\Operations\OperationalErrorSanitizer;
use App\Support\Period\PeriodContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessCardImport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $companyId,
        public readonly int $periodId,
        public readonly string $batchId,
    ) {}

    public function handle(
        ImportFileReader $reader,
        ImportRowImporterResolver $resolver,
        OperationalErrorSanitizer $sanitizer,
    ): void
    {
        PeriodPermissionContext::clear();
        PeriodContext::useSystem($this->companyId, $this->periodId);

        try {
            PeriodContext::ensureWritable();
            $batch = CardImportBatch::query()->findOrFail($this->batchId);

            if ($batch->status === 'done') {
                return;
            }

            $batch->update([
                'status' => 'processing',
                'started_at' => now(),
                'failure_message' => null,
            ]);

            CardImportError::query()->where('batch_id', $batch->id)->delete();

            try {
                $rows = $reader->rows($batch->source_disk, $batch->source_path, $batch->original_name);
                $importer = $resolver->resolve($batch->type);
                $validRows = [];
                $validationErrors = [];

                foreach ($rows as $index => $sourceRow) {
                    $rowNo = $index + 2;
                    $mapped = ImportMapping::map($sourceRow, $batch->mapping);
                    $validation = $importer->validate($mapped);

                    if (! $validation->valid) {
                        foreach ($validation->errors as $column => $message) {
                            $validationErrors[] = [
                                'batch_id' => $batch->id,
                                'row_no' => $rowNo,
                                'column_name' => (string) $column,
                                'value' => isset($mapped[$column]) ? (string) $mapped[$column] : null,
                                'message' => (string) $message,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        continue;
                    }

                    $validRows[] = [$rowNo, $mapped];
                }

                if ($validationErrors !== []) {
                    CardImportError::query()->insert($validationErrors);
                }

                if (($batch->type === 'opening_stock' || $batch->error_mode === 'cancel_all') && $errors !== []) {
                    $batch->update([
                        'status' => 'failed',
                        'total_rows' => count($rows),
                        'success_rows' => 0,
                        'error_rows' => count(array_unique(array_column($validationErrors, 'row_no'))),
                        'finished_at' => now(),
                        'failure_message' => 'Doğrulama hatası nedeniyle hiçbir satır uygulanmadı.',
                    ]);

                    return;
                }

                $actor = $batch->created_by
                    ? User::query()->where('is_active', true)->findOrFail($batch->created_by)
                    : null;

                if (! $actor) {
                    throw new \Illuminate\Auth\Access\AuthorizationException(
                        'İçe aktarma için aktif actor kullanıcı bulunamadı.',
                    );
                }

                $hasCompanyAccess = DB::connection('master')
                    ->table('company_user')
                    ->where('company_id', $this->companyId)
                    ->where('user_id', $actor->id)
                    ->exists();
                $periodAccess = DB::connection('master')
                    ->table('period_user_access')
                    ->where('period_id', $this->periodId)
                    ->where('user_id', $actor->id)
                    ->where('is_active', true)
                    ->first(['permission_overrides']);

                if (! $hasCompanyAccess || ! $periodAccess) {
                    throw new \Illuminate\Auth\Access\AuthorizationException(
                        'İçe aktarma actor kullanıcısının şirket/dönem erişimi artık aktif değil.',
                    );
                }

                $overrides = $periodAccess->permission_overrides;

                if (is_string($overrides)) {
                    $overrides = json_decode($overrides, true) ?: [];
                }

                PeriodPermissionContext::use(is_array($overrides) ? $overrides : []);

                try {
                    MutationAuthorizer::runAs($actor, function () use ($batch, $importer, $validRows): void {
                    if ($batch->type === 'opening_stock') {
                        if ($batch->error_mode !== 'cancel_all') {
                            throw new \RuntimeException('Açılış stok importu yalnız tümünü iptal et modunda çalışır.');
                        }

                        $openingDate = $batch->opening_date?->toDateString();

                        if (! $openingDate) {
                            throw new \RuntimeException('Açılış tarihi bulunamadı.');
                        }

                        $rows = array_map(
                            fn (array $item): array => $item[1],
                            $validRows,
                        );

                        app(ImportOpeningStock::class)->handle(
                            $rows,
                            $openingDate,
                            $batch->id,
                            $batch->created_by,
                            $batch->created_by_name,
                        );

                        return;
                    }

                    DB::connection('period')->transaction(function () use ($importer, $validRows): void {
                        foreach ($validRows as [, $row]) {
                            $importer->import($row);
                        }
                    });
                    });
                } finally {
                    PeriodPermissionContext::clear();
                }

                $errorRowCount = count(array_unique(array_column($validationErrors, 'row_no')));

                $batch->update([
                    'status' => 'done',
                    'total_rows' => count($rows),
                    'success_rows' => count($validRows),
                    'error_rows' => $errorRowCount,
                    'finished_at' => now(),
                ]);

                AuditContext::period(
                    'Kart içe aktarma tamamlandı.',
                    [
                        'batch_id' => $batch->id,
                        'type' => $batch->type,
                        'total_rows' => count($rows),
                        'success_rows' => count($validRows),
                        'error_rows' => $errorRowCount,
                    ],
                    null,
                    'card_import_completed',
                );
            } catch (Throwable $exception) {
                $batch->update([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'failure_message' => $sanitizer->summarize($exception),
                ]);

                throw $exception;
            }
        } finally {
            PeriodPermissionContext::clear();
            PeriodContext::clear();
        }
    }
}
