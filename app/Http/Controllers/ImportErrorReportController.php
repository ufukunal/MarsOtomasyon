<?php

namespace App\Http\Controllers;

use App\Models\Period\CardImportBatch;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
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

            foreach (['Satır', 'Kolon', 'Değer', 'Hata'] as $index => $heading) {
                $sheet->getCell([$index + 1, 1])->setValueExplicit($heading, DataType::TYPE_STRING);
            }

            $row = 2;

            foreach ($batch->errors as $error) {
                $sheet->getCell([1, $row])->setValueExplicit((int) $error->row_no, DataType::TYPE_NUMERIC);
                $sheet->getCell([2, $row])->setValueExplicit((string) $error->column_name, DataType::TYPE_STRING);
                $sheet->getCell([3, $row])->setValueExplicit((string) $error->value, DataType::TYPE_STRING);
                $sheet->getCell([4, $row])->setValueExplicit((string) $error->message, DataType::TYPE_STRING);
                $row++;
            }

            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, "import-errors-{$batch->id}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
