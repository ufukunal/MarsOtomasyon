<?php

namespace App\Modules\Products\Labels\Http\Controllers;

use App\Foundation\Features\FeatureKey;
use App\Foundation\Features\FeatureRegistry;
use App\Http\Controllers\Controller;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Products\Labels\LabelRenderService;
use App\Modules\Products\Labels\Models\LabelPrint;
use App\Modules\Products\Labels\Models\LabelTemplate;
use App\Modules\Products\Labels\Models\PrinterProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class LabelController extends Controller
{
    public function __construct(private readonly FeatureRegistry $features) {}

    public function storeTemplate(Request $request, ActiveCompanyContext $companyContext): JsonResponse
    {
        $this->ensureEnabled();
        $companyId = (int) $companyContext->requireCompany()->getKey();
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique('label_templates', 'code')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:160'],
            'target_type' => ['required', Rule::in(['product', 'warehouse', 'location', 'package', 'shipment'])],
            'format' => ['required', Rule::in(['pdf', 'zpl', 'tspl'])],
            'width_mm' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
            'height_mm' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
            'dpi' => ['nullable', 'integer', 'min:72', 'max:1200'],
            'body' => ['required', 'string', 'max:65535'],
            'config' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template = LabelTemplate::query()->create(['company_id' => $companyId, ...$data]);

        return response()->json(['data' => $template], 201);
    }

    public function storePrinterProfile(Request $request, ActiveCompanyContext $companyContext): JsonResponse
    {
        $this->ensureEnabled();
        $companyId = (int) $companyContext->requireCompany()->getKey();
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique('printer_profiles', 'code')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:160'],
            'driver' => ['required', Rule::in(['pdf', 'zpl', 'tspl'])],
            'width_mm' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
            'height_mm' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
            'dpi' => ['nullable', 'integer', 'min:72', 'max:1200'],
            'config' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $profile = PrinterProfile::query()->create(['company_id' => $companyId, ...$data]);

        return response()->json(['data' => $profile], 201);
    }

    public function render(Request $request, ActiveCompanyContext $companyContext, LabelRenderService $service): JsonResponse
    {
        $this->ensureEnabled();
        $companyId = (int) $companyContext->requireCompany()->getKey();
        $data = $request->validate([
            'template_id' => ['required', 'integer'],
            'target_id' => ['required', 'integer', 'min:1'],
            'printer_profile_id' => ['nullable', 'integer'],
            'barcode_id' => ['nullable', 'integer'],
        ]);

        $template = LabelTemplate::query()->whereKey($data['template_id'])->where('company_id', $companyId)->firstOrFail();
        $printer = isset($data['printer_profile_id'])
            ? PrinterProfile::query()->whereKey($data['printer_profile_id'])->where('company_id', $companyId)->firstOrFail()
            : null;

        $print = $service->render($template, (int) $data['target_id'], $printer, isset($data['barcode_id']) ? (int) $data['barcode_id'] : null);

        return response()->json([
            'data' => [
                'id' => $print->getKey(),
                'format' => $print->format,
                'content_hash' => $print->content_hash,
                'output_url' => route('inventory.labels.output', $print),
            ],
        ], 201);
    }

    public function reprint(LabelPrint $labelPrint, LabelRenderService $service): JsonResponse
    {
        $this->ensureEnabled();
        $print = $service->reprint($labelPrint);

        return response()->json([
            'data' => [
                'id' => $print->getKey(),
                'reprint_of_id' => $print->reprint_of_id,
                'content_hash' => $print->content_hash,
                'output_url' => route('inventory.labels.output', $print),
            ],
        ], 201);
    }

    public function output(LabelPrint $labelPrint, LabelRenderService $service): Response
    {
        $this->ensureEnabled();
        $output = $service->output($labelPrint);
        $contentType = $labelPrint->format === 'pdf' ? 'application/pdf' : 'text/plain; charset=UTF-8';

        return response($output, 200, [
            'Content-Type' => $contentType,
            'X-Content-SHA256' => $labelPrint->content_hash,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function ensureEnabled(): void
    {
        abort_unless($this->features->enabled(FeatureKey::BarcodeThermalLabels), 404);
    }
}
