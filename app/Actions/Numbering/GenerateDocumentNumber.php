<?php

namespace App\Actions\Numbering;

use App\Exceptions\PeriodYearMismatchException;
use App\Models\NumberSeries;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\DB;

final class GenerateDocumentNumber
{
    public function handle(string $documentType, ?int $year = null): string
    {
        PeriodContext::ensureWritable();

        $activeYear = PeriodContext::year();
        $year ??= $activeYear ?? now()->year;

        if ($activeYear !== null && $year !== $activeYear) {
            throw new PeriodYearMismatchException(
                "Numara yılı {$year}, aktif dönem yılı {$activeYear} ile eşleşmiyor.",
            );
        }

        return DB::connection('period')->transaction(function () use ($documentType, $year): string {
            NumberSeries::query()->firstOrCreate(
                [
                    'document_type' => $documentType,
                    'year' => $year,
                ],
                [
                    'prefix' => config("numbering.prefixes.{$documentType}", 'DOC'),
                    'last_number' => 0,
                    'padding' => 5,
                ],
            );

            $series = NumberSeries::query()
                ->where('document_type', $documentType)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $series->last_number++;
            $series->save();

            return sprintf(
                '%s-%d-%s',
                $series->prefix,
                $series->year,
                str_pad((string) $series->last_number, $series->padding, '0', STR_PAD_LEFT),
            );
        });
    }
}
