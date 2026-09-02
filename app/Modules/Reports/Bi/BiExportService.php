<?php

namespace App\Modules\Reports\Bi;

use DomainException;
use Illuminate\Support\Facades\DB;

final class BiExportService
{
    public function __construct(private readonly BiDatasetRegistry $datasets) {}

    /**
     * @param list<string> $requestedFields
     * @return array{run_id:int,content:string,sha256:string,row_count:int,watermark:?string,format:string,schema_version:int}
     */
    public function export(int $companyId, string $datasetKey, string $format, array $requestedFields, ?string $watermark = null, bool $includePii = false, ?int $scheduleId = null): array
    {
        $dataset = $this->datasets->get($datasetKey);
        $format = strtolower(trim($format));
        if (! in_array($format, ['csv', 'json'], true)) {
            throw new DomainException('Unsupported BI export format.');
        }

        $definition = $dataset->fields();
        $requestedFields = array_values(array_unique($requestedFields));
        if ($requestedFields === []) {
            throw new DomainException('At least one BI export field is required.');
        }
        foreach ($requestedFields as $field) {
            if (! isset($definition[$field])) {
                throw new DomainException('BI export field is not allow-listed: '.$field);
            }
            if ($definition[$field]['pii'] && ! $includePii) {
                throw new DomainException('BI export PII field requires explicit authorization: '.$field);
            }
        }

        $runId = (int) DB::table('bi_export_runs')->insertGetId([
            'company_id' => $companyId,
            'schedule_id' => $scheduleId,
            'dataset_key' => strtolower(trim($dataset->key())),
            'schema_version' => $dataset->schemaVersion(),
            'format' => $format,
            'fields' => json_encode($requestedFields, JSON_THROW_ON_ERROR),
            'input_watermark' => $watermark,
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $rows = [];
            foreach ($dataset->rows($companyId, $watermark) as $sourceRow) {
                $sourceCompany = $sourceRow['company_id'] ?? null;
                if ((int) $sourceCompany !== $companyId) {
                    throw new DomainException('BI dataset emitted a cross-company row.');
                }
                $row = [];
                foreach ($requestedFields as $field) {
                    $row[$field] = $sourceRow[$field] ?? null;
                }
                $rows[] = $row;
            }

            $content = $format === 'json'
                ? json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : $this->csv($requestedFields, $rows);
            $hash = hash('sha256', $content);
            $nextWatermark = $dataset->nextWatermark();

            DB::transaction(function () use ($runId, $rows, $hash, $content, $nextWatermark, $scheduleId): void {
                DB::table('bi_export_runs')->where('id', $runId)->update([
                    'status' => 'succeeded',
                    'row_count' => count($rows),
                    'artifact_sha256' => $hash,
                    'artifact_size_bytes' => strlen($content),
                    'output_watermark' => $nextWatermark,
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($scheduleId !== null) {
                    DB::table('bi_export_schedules')->where('id', $scheduleId)->update([
                        'watermark' => $nextWatermark,
                        'last_run_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

            return [
                'run_id' => $runId,
                'content' => $content,
                'sha256' => $hash,
                'row_count' => count($rows),
                'watermark' => $nextWatermark,
                'format' => $format,
                'schema_version' => $dataset->schemaVersion(),
            ];
        } catch (\Throwable $exception) {
            DB::table('bi_export_runs')->where('id', $runId)->update([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
            throw $exception;
        }
    }

    /** @param list<string> $fields @param list<array<string, mixed>> $rows */
    private function csv(array $fields, array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new DomainException('BI CSV stream could not be opened.');
        }
        fputcsv($stream, $fields);
        foreach ($rows as $row) {
            $values = [];
            foreach ($fields as $field) {
                $value = $row[$field] ?? null;
                $values[] = is_scalar($value) || $value === null
                    ? $value
                    : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            fputcsv($stream, $values);
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        if (! is_string($content)) {
            throw new DomainException('BI CSV content could not be read.');
        }

        return $content;
    }
}
