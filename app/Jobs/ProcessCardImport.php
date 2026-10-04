<?php

namespace App\Jobs;

use App\Models\Period\CardImportBatch;
use App\Models\Period\CardImportError;
use App\Models\User;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Import\ImportFileReader;
use App\Support\Import\ImportMapping;
use App\Support\Import\ImportRowImporterResolver;
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

    public function handle(ImportFileReader $reader, ImportRowImporterResolver $resolver): void
    {
        PeriodContext::useSystem($this->companyId, $this->periodId);

        try {
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
            $errors = [];

            foreach ($rows as $index => $sourceRow) {
                $rowNo = $index + 2;
                $mapped = ImportMapping::map($sourceRow, $batch->mapping);
                $validation = $importer->validate($mapped);

                if (! $validation->valid) {
                    foreach ($validation->errors as $column => $message) {
                        $errors[] = [
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

            if ($errors !== []) {
                CardImportError::query()->insert($errors);
            }

            if ($batch->error_mode === 'cancel_all' && $errors !== []) {
                $batch->update([
                    'status' => 'failed',
                    'total_rows' => count($rows),
                    'success_rows' => 0,
                    'error_rows' => count(array_unique(array_column($errors, 'row_no'))),
                    'finished_at' => now(),
                    'failure_message' => 'Doğrulama hatası nedeniyle hiçbir satır uygulanmadı.',
                ]);

                return;
            }

            $actor = $batch->created_by
                ? User::query()->findOrFail($batch->created_by)
                : null;

            MutationAuthorizer::runAs($actor, function () use ($importer, $validRows): void {
                DB::connection('period')->transaction(function () use ($importer, $validRows): void {
                    foreach ($validRows as [, $row]) {
                        $importer->import($row);
                    }
                });
            });

            $errorRowCount = count(array_unique(array_column($errors, 'row_no')));

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
                    'failure_message' => $exception->getMessage(),
                ]);

                throw $exception;
            }
        } finally {
            PeriodContext::clear();
        }
    }
}
