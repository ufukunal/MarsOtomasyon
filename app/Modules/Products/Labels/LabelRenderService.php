<?php

namespace App\Modules\Products\Labels;

use App\Modules\Products\Models\Barcode;
use App\Modules\Products\Models\Product;
use Dompdf\Dompdf;
use Dompdf\Options;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LabelRenderService
{
    public function createTemplate(
        int $companyId,
        string $key,
        string $name,
        string $format,
        string $bodyTemplate,
        int $widthMm = 100,
        int $heightMm = 50,
    ): LabelTemplate {
        $key = strtolower(trim($key));
        $name = trim($name);
        $format = strtolower(trim($format));
        if ($companyId < 1 || $key === '' || $name === '' || trim($bodyTemplate) === '') {
            throw new DomainException('Label template identity and body are required.');
        }
        if (! in_array($format, ['pdf', 'zpl', 'tspl'], true)) {
            throw new DomainException('Unsupported label output format.');
        }
        if ($widthMm < 10 || $widthMm > 1000 || $heightMm < 10 || $heightMm > 1000) {
            throw new DomainException('Label dimensions are outside supported bounds.');
        }

        return LabelTemplate::query()->create([
            'company_id' => $companyId,
            'key' => mb_substr($key, 0, 80),
            'name' => mb_substr($name, 0, 160),
            'target_type' => 'product',
            'output_format' => $format,
            'width_mm' => $widthMm,
            'height_mm' => $heightMm,
            'body_template' => $bodyTemplate,
            'is_active' => true,
        ]);
    }

    public function renderProduct(int $companyId, int $productId, int $templateId, ?int $reprintOfId = null): LabelRenderResult
    {
        $template = LabelTemplate::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->find($templateId);
        if (! $template instanceof LabelTemplate || (string) $template->target_type !== 'product') {
            throw new DomainException('Active product label template was not found for company.');
        }
        $product = Product::query()->where('company_id', $companyId)->find($productId);
        if (! $product instanceof Product) {
            throw new DomainException('Label product was not found for company.');
        }
        $barcode = Barcode::query()
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
        if (! $barcode instanceof Barcode) {
            throw new DomainException('Product label requires an existing Barcode identity.');
        }

        $format = (string) $template->output_format;
        $barcodeValue = trim((string) $barcode->barcode);
        if ($barcodeValue === '') {
            throw new DomainException('Persisted product Barcode identity is empty.');
        }
        if (in_array($format, ['zpl', 'tspl'], true) && preg_match('/^[A-Za-z0-9._\-]+$/', $barcodeValue) !== 1) {
            throw new DomainException('Barcode contains characters unsafe for the selected printer language.');
        }

        if ($reprintOfId !== null) {
            $original = DB::table('label_render_requests')
                ->where('company_id', $companyId)
                ->where('id', $reprintOfId)
                ->where('target_type', 'product')
                ->where('target_id', $productId)
                ->where('label_template_id', $templateId)
                ->first();
            if ($original === null) {
                throw new DomainException('Reprint source does not match label target and template.');
            }
        }

        $values = [
            '{{product.code}}' => (string) $product->code,
            '{{product.name}}' => (string) $product->name,
            '{{barcode}}' => $barcodeValue,
        ];
        $rendered = (string) $template->body_template;
        foreach ($values as $placeholder => $value) {
            $rendered = str_replace($placeholder, $this->safeDynamicValue($value, $format), $rendered);
        }

        [$content, $mimeType] = match ($format) {
            'pdf' => [$this->renderPdf($rendered, (int) $template->width_mm, (int) $template->height_mm), 'application/pdf'],
            'zpl' => [$rendered, 'text/plain; charset=UTF-8'],
            'tspl' => [$rendered, 'text/plain; charset=UTF-8'],
            default => throw new DomainException('Unsupported label output format.'),
        };
        if ($content === '' || strlen($content) > 2_097_152) {
            throw new RuntimeException('Rendered label output is empty or exceeds the 2 MB safety limit.');
        }
        $sha256 = hash('sha256', $content);
        $actorId = Auth::id();
        $payload = [
            'product_id' => $productId,
            'product_code' => (string) $product->code,
            'barcode_id' => (int) $barcode->getKey(),
            'barcode' => $barcodeValue,
            'template_id' => $templateId,
        ];
        $requestId = (int) DB::table('label_render_requests')->insertGetId([
            'company_id' => $companyId,
            'label_template_id' => $templateId,
            'target_type' => 'product',
            'target_id' => $productId,
            'barcode_id' => $barcode->getKey(),
            'output_format' => $format,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'output_sha256' => $sha256,
            'rendered_by_user_id' => is_int($actorId) ? $actorId : null,
            'reprint_of_id' => $reprintOfId,
            'rendered_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new LabelRenderResult($requestId, $format, $mimeType, $content, $sha256);
    }

    private function safeDynamicValue(string $value, string $format): string
    {
        $value = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value));

        return match ($format) {
            'pdf' => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'zpl' => str_replace(['^', '~'], ' ', $value),
            'tspl' => str_replace('"', "'", $value),
            default => $value,
        };
    }

    private function renderPdf(string $html, int $widthMm, int $heightMm): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $mmToPoints = 72 / 25.4;
        $dompdf->setPaper([0, 0, $widthMm * $mmToPoints, $heightMm * $mmToPoints]);
        $dompdf->render();
        $output = $dompdf->output();

        return is_string($output) ? $output : throw new RuntimeException('PDF label renderer did not return binary output.');
    }
}
