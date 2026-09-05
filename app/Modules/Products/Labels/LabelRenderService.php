<?php

namespace App\Modules\Products\Labels;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Products\Labels\Models\LabelPrint;
use App\Modules\Products\Labels\Models\LabelTemplate;
use App\Modules\Products\Labels\Models\PrinterProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class LabelRenderService
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private LabelTargetResolver $targets,
        private LabelRenderer $renderer,
        private AuditRecorder $audit,
    ) {}

    public function render(LabelTemplate $template, int $targetId, ?PrinterProfile $printer = null, ?int $barcodeId = null): LabelPrint
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $this->assertTemplate($template, $companyId);
        $this->assertPrinter($printer, $template, $companyId);

        return DB::transaction(function () use ($template, $targetId, $printer, $barcodeId, $companyId): LabelPrint {
            $resolved = $this->targets->resolve($companyId, $template->target_type, $targetId, $barcodeId);
            $templateSnapshot = $this->templateSnapshot($template);
            $printerSnapshot = $printer === null ? null : $this->printerSnapshot($printer);
            $output = $this->renderer->render($resolved['payload'], $templateSnapshot, $printerSnapshot);
            $hash = hash('sha256', $output);

            $print = LabelPrint::query()->create([
                'company_id' => $companyId,
                'label_template_id' => $template->getKey(),
                'printer_profile_id' => $printer?->getKey(),
                'target_type' => $template->target_type,
                'target_id' => $targetId,
                'barcode_id' => $resolved['barcode_id'],
                'format' => $template->format,
                'payload_snapshot' => $resolved['payload'],
                'template_snapshot' => $templateSnapshot,
                'printer_snapshot' => $printerSnapshot,
                'output_base64' => base64_encode($output),
                'content_hash' => $hash,
                'created_by_user_id' => Auth::id(),
            ]);

            $this->audit->record(
                AuditAction::LabelRendered,
                AuditTargetType::LabelPrint,
                $print->getKey(),
                after: ['content_hash' => $hash, 'format' => $template->format],
                metadata: ['target_type' => $template->target_type, 'target_id' => $targetId],
            );

            return $print;
        });
    }

    public function reprint(LabelPrint $original): LabelPrint
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        if ((int) $original->company_id !== $companyId) {
            abort(404);
        }

        $output = $this->output($original);

        return DB::transaction(function () use ($original, $output, $companyId): LabelPrint {
            $print = LabelPrint::query()->create([
                'company_id' => $companyId,
                'label_template_id' => $original->label_template_id,
                'printer_profile_id' => $original->printer_profile_id,
                'target_type' => $original->target_type,
                'target_id' => $original->target_id,
                'barcode_id' => $original->barcode_id,
                'format' => $original->format,
                'payload_snapshot' => $original->payload_snapshot,
                'template_snapshot' => $original->template_snapshot,
                'printer_snapshot' => $original->printer_snapshot,
                'output_base64' => base64_encode($output),
                'content_hash' => $original->content_hash,
                'reprint_of_id' => $original->reprint_of_id ?: $original->getKey(),
                'created_by_user_id' => Auth::id(),
            ]);

            $this->audit->record(
                AuditAction::LabelReprinted,
                AuditTargetType::LabelPrint,
                $print->getKey(),
                after: ['content_hash' => $print->content_hash, 'reprint_of_id' => $print->reprint_of_id],
                metadata: ['original_print_id' => $original->getKey()],
            );

            return $print;
        });
    }

    public function output(LabelPrint $print): string
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        if ((int) $print->company_id !== $companyId) {
            abort(404);
        }

        $output = base64_decode($print->output_base64, true);
        if ($output === false || ! hash_equals($print->content_hash, hash('sha256', $output))) {
            throw new RuntimeException('Persisted label output integrity check failed.');
        }

        return $output;
    }

    private function assertTemplate(LabelTemplate $template, int $companyId): void
    {
        if ((int) $template->company_id !== $companyId) {
            abort(404);
        }
        if (! $template->is_active) {
            throw ValidationException::withMessages(['template_id' => 'Label template is inactive.']);
        }
    }

    private function assertPrinter(?PrinterProfile $printer, LabelTemplate $template, int $companyId): void
    {
        if ($printer === null) {
            return;
        }
        if ((int) $printer->company_id !== $companyId) {
            abort(404);
        }
        if (! $printer->is_active) {
            throw ValidationException::withMessages(['printer_profile_id' => 'Printer profile is inactive.']);
        }
        if ($printer->driver !== $template->format) {
            throw ValidationException::withMessages(['printer_profile_id' => 'Printer driver does not match template format.']);
        }
    }

    /** @return array<string, mixed> */
    private function templateSnapshot(LabelTemplate $template): array
    {
        return [
            'id' => $template->getKey(),
            'code' => $template->code,
            'target_type' => $template->target_type,
            'format' => $template->format,
            'width_mm' => $template->width_mm,
            'height_mm' => $template->height_mm,
            'dpi' => $template->dpi,
            'body' => $template->body,
            'config' => $template->config,
        ];
    }

    /** @return array<string, mixed> */
    private function printerSnapshot(PrinterProfile $printer): array
    {
        return [
            'id' => $printer->getKey(),
            'code' => $printer->code,
            'driver' => $printer->driver,
            'width_mm' => $printer->width_mm,
            'height_mm' => $printer->height_mm,
            'dpi' => $printer->dpi,
            'config' => $printer->config,
        ];
    }
}
