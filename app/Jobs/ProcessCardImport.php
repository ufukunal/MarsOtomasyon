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
use Illuminate\Auth\Access\AuthorizationException;
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
    ): void {
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
                [$actor, $overrides] = $this->resolveActorContext($batch);
                PeriodPermissionContext::use($overrides);

                MutationAuthorizer::runAs(
                    $actor,
                    fn () => MutationAuthorizer::authorize('imports.create'),
                );

                $importer = $resolver->resolve($batch->type);
                $invalidRows = [];
                $errorBuffer = [];
                $totalRows = 0;
                $validRowCount = 0;

                foreach ($reader->iterate(
                    $batch->source_disk,
                    $batch->source_path,
                    $batch->original_name,
                ) as $index => $sourceRow) {
                    $rowNo = $index + 2;
                    $totalRows++;
                    $mapped = ImportMapping::map($sourceRow, $batch->mapping);
                    $validation = $importer->validate($mapped);

                    if ($validation->valid) {
                        $validRowCount++;

                        continue;
                    }

                    $invalidRows[$rowNo] = true;

                    foreach ($validation->errors as $column => $message) {
                        $errorBuffer[] = [
                            'batch_id' => $batch->id,
                            'row_no' => $rowNo,
                            'column_name' => (string) $column,
                            'value' => isset($mapped[$column]) ? (string) $mapped[$column] : null,
                            'message' => (string) $message,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    if (count($errorBuffer) >= 500) {
                        CardImportError::query()->insert($errorBuffer);
                        $errorBuffer = [];
                    }
                }

                if ($errorBuffer !== []) {
                    CardImportError::query()->insert($errorBuffer);
                }

                $errorRowCount = count($invalidRows);

                if (($batch->type === 'opening_stock' || $batch->error_mode === 'cancel_all')
                    && $errorRowCount > 0) {
                    $batch->update([
                        'status' => 'failed',
                        'total_rows' => $totalRows,
                        'success_rows' => 0,
                        'error_rows' => $errorRowCount,
                        'finished_at' => now(),
                        'failure_message' => 'Doğrulama hatası nedeniyle hiçbir satır uygulanmadı.',
                    ]);

                    return;
                }

                MutationAuthorizer::runAs($actor, function () use (
                    $batch,
                    $importer,
                    $invalidRows,
                    $reader,
                ): void {
                    if ($batch->type === 'opening_stock') {
                        if ($batch->error_mode !== 'cancel_all') {
                            throw new \RuntimeException(
                                'Açılış stok importu yalnız tümünü iptal et modunda çalışır.',
                            );
                        }

                        $openingDate = $batch->opening_date?->toDateString();

                        if (! $openingDate) {
                            throw new \RuntimeException('Açılış tarihi bulunamadı.');
                        }

                        $rows = [];

                        foreach ($reader->iterate(
                            $batch->source_disk,
                            $batch->source_path,
                            $batch->original_name,
                        ) as $index => $sourceRow) {
                            if (isset($invalidRows[$index + 2])) {
                                continue;
                            }

                            $rows[] = ImportMapping::map($sourceRow, $batch->mapping);
                        }

                        app(ImportOpeningStock::class)->handle(
                            $rows,
                            $openingDate,
                            $batch->id,
                            $batch->created_by,
                            $batch->created_by_name,
                        );

                        return;
                    }

                    DB::connection('period')->transaction(function () use (
                        $batch,
                        $importer,
                        $invalidRows,
                        $reader,
                    ): void {
                        foreach ($reader->iterate(
                            $batch->source_disk,
                            $batch->source_path,
                            $batch->original_name,
                        ) as $index => $sourceRow) {
                            if (isset($invalidRows[$index + 2])) {
                                continue;
                            }

                            $importer->import(
                                ImportMapping::map($sourceRow, $batch->mapping),
                            );
                        }
                    }, attempts: 3);
                });

                $batch->update([
                    'status' => 'done',
                    'total_rows' => $totalRows,
                    'success_rows' => $validRowCount,
                    'error_rows' => $errorRowCount,
                    'finished_at' => now(),
                ]);

                AuditContext::period(
                    'Kart içe aktarma tamamlandı.',
                    [
                        'batch_id' => $batch->id,
                        'type' => $batch->type,
                        'total_rows' => $totalRows,
                        'success_rows' => $validRowCount,
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
            } finally {
                PeriodPermissionContext::clear();
            }
        } finally {
            PeriodPermissionContext::clear();
            PeriodContext::clear();
        }
    }

    /**
     * @return array{0:User,1:array<string,mixed>}
     */
    private function resolveActorContext(CardImportBatch $batch): array
    {
        $actor = $batch->created_by
            ? User::query()->where('is_active', true)->find($batch->created_by)
            : null;

        if (! $actor) {
            throw new AuthorizationException(
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
            throw new AuthorizationException(
                'İçe aktarma actor kullanıcısının şirket/dönem erişimi artık aktif değil.',
            );
        }

        $overrides = $periodAccess->permission_overrides;

        if (is_string($overrides)) {
            $overrides = json_decode($overrides, true) ?: [];
        }

        return [$actor, is_array($overrides) ? $overrides : []];
    }
}
