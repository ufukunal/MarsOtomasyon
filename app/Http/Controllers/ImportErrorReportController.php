<?php

namespace App\Http\Controllers;

use App\Models\Period\CardImportBatch;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportErrorReportController extends Controller
{
    public function __invoke(string $batchId): StreamedResponse
    {
        abort_unless(auth()->user()?->can('imports.view'), 403);

        $batch = CardImportBatch::query()->with('errors')->findOrFail($batchId);

        return response()->streamDownload(function () use ($batch): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->fromArray(
                ['Satır', 'Kolon', 'Değer', 'Hata'],
                null,
                'A1',
            );

            $row = 2;

            foreach ($batch->errors as $error) {
                $sheet->fromArray([
                    $error->row_no,
                    $error->column_name,
                    $error->value,
                    $error->message,
                ], null, "A{$row}");

                $row++;
            }

            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, "import-errors-{$batch->id}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
