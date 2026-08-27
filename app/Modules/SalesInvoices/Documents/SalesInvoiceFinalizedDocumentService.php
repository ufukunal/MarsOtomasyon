<?php

namespace App\Modules\SalesInvoices\Documents;

use App\Foundation\Clock\Clock;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\FileAsset;
use App\Modules\SalesInvoices\Enums\SalesInvoiceStatus;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesInvoices\Models\SalesInvoiceFinalizedDocument;
use App\Modules\SalesInvoices\Models\SalesInvoiceLine;
use DateTimeInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Throwable;

final readonly class SalesInvoiceFinalizedDocumentService
{
    public const RENDERER_VERSION = 'sales-invoice-pdf.v1';

    public function __construct(
        private ActiveCompanyContext $companyContext,
        private Clock $clock,
    ) {}

    public function getOrCreate(int $invoiceId): SalesInvoiceFinalizedDocument
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $storedKey = null;

        try {
            return DB::transaction(function () use ($companyId, $invoiceId, &$storedKey): SalesInvoiceFinalizedDocument {
                $invoice = SalesInvoice::query()
                    ->where('company_id', $companyId)
                    ->whereKey($invoiceId)
                    ->lockForUpdate()
                    ->with(['company', 'lines'])
                    ->firstOrFail();

                if (! in_array($invoice->statusEnum(), [SalesInvoiceStatus::Finalized, SalesInvoiceStatus::Cancelled], true)) {
                    throw new LogicException('Finalized PDF requires a finalized or cancelled sales invoice.');
                }

                $finalizedAt = $invoice->getAttribute('finalized_at');
                if (! $finalizedAt instanceof DateTimeInterface) {
                    throw new LogicException('Finalized PDF requires a valid finalization timestamp.');
                }

                $existing = SalesInvoiceFinalizedDocument::query()
                    ->where('company_id', $companyId)
                    ->where('sales_invoice_id', $invoiceId)
                    ->where('renderer_version', self::RENDERER_VERSION)
                    ->lockForUpdate()
                    ->with('fileAsset')
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                $sourceFingerprint = $this->sourceFingerprint($invoice);
                $pdfBytes = $this->render($invoice, $sourceFingerprint);
                $pdfSha256 = hash('sha256', $pdfBytes);
                $storedKey = sprintf(
                    'companies/%d/generated/sales-invoices/%d/%s-%s.pdf',
                    $companyId,
                    $invoiceId,
                    self::RENDERER_VERSION,
                    $pdfSha256,
                );

                if (! Storage::disk('local')->put($storedKey, $pdfBytes)) {
                    throw new LogicException('Finalized invoice PDF could not be persisted to private storage.');
                }

                $asset = FileAsset::query()->create([
                    'company_id' => $companyId,
                    'uploaded_by_user_id' => null,
                    'storage_disk' => 'local',
                    'storage_key' => $storedKey,
                    'original_name' => $this->fileName((string) $invoice->number),
                    'mime_type' => 'application/pdf',
                    'client_extension' => 'pdf',
                    'size_bytes' => strlen($pdfBytes),
                    'sha256' => $pdfSha256,
                ]);

                $assetId = $asset->getKey();
                if (! is_int($assetId)) {
                    throw new LogicException('Finalized invoice PDF file asset persistence did not return an integer key.');
                }

                $document = SalesInvoiceFinalizedDocument::query()->create([
                    'company_id' => $companyId,
                    'sales_invoice_id' => $invoiceId,
                    'file_asset_id' => $assetId,
                    'renderer_version' => self::RENDERER_VERSION,
                    'source_fingerprint' => $sourceFingerprint,
                    'pdf_sha256' => $pdfSha256,
                    'generated_at' => $this->clock->now(),
                ]);

                return $document->setRelation('fileAsset', $asset);
            });
        } catch (Throwable $exception) {
            if ($storedKey !== null) {
                Storage::disk('local')->delete($storedKey);
            }

            throw $exception;
        }
    }

    public function verifiedBytes(SalesInvoiceFinalizedDocument $document): string
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        if ((int) $document->company_id !== $companyId) {
            throw new LogicException('Finalized invoice PDF belongs to another company.');
        }

        $asset = $document->relationLoaded('fileAsset') ? $document->fileAsset : $document->fileAsset()->first();
        abort_unless($asset instanceof FileAsset && $asset->archived_at === null, 410, 'Finalized invoice PDF metadata is unavailable.');
        abort_unless($asset->storage_disk === 'local', 410, 'Finalized invoice PDF storage contract is invalid.');
        abort_unless(Storage::disk('local')->exists((string) $asset->storage_key), 410, 'Finalized invoice PDF storage object is missing.');

        $bytes = Storage::disk('local')->get((string) $asset->storage_key);
        if (! is_string($bytes)) {
            abort(410, 'Finalized invoice PDF storage object could not be read.');
        }
        abort_unless(str_starts_with($bytes, '%PDF-'), 410, 'Finalized invoice PDF signature is invalid.');

        $sha256 = hash('sha256', $bytes);
        abort_unless(
            hash_equals((string) $document->pdf_sha256, $sha256)
            && hash_equals((string) $asset->sha256, $sha256),
            410,
            'Finalized invoice PDF integrity check failed.',
        );

        return $bytes;
    }

    private function render(SalesInvoice $invoice, string $sourceFingerprint): string
    {
        $html = view('sales-invoices.pdf.finalized', [
            'invoice' => $invoice,
            'rendererVersion' => self::RENDERER_VERSION,
            'sourceFingerprint' => $sourceFingerprint,
        ])->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();
        $output = $dompdf->output();

        if ($output === '' || ! str_starts_with($output, '%PDF-')) {
            throw new LogicException('Dompdf did not produce a valid sales invoice PDF payload.');
        }

        return $output;
    }

    private function sourceFingerprint(SalesInvoice $invoice): string
    {
        $company = $invoice->company;
        if (! $company instanceof Company) {
            throw new LogicException('Finalized invoice PDF requires the invoice company relation.');
        }

        $finalizedAt = $invoice->getAttribute('finalized_at');
        if (! $finalizedAt instanceof DateTimeInterface) {
            throw new LogicException('Finalized invoice PDF requires a valid finalization timestamp.');
        }

        $lines = $invoice->lines->map(static function (SalesInvoiceLine $line): array {
            return [
                'position' => (int) $line->position,
                'product_id' => (int) $line->product_id,
                'product_code' => (string) $line->product_code,
                'product_name' => (string) $line->product_name,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'price_basis' => (string) $line->getRawOriginal('price_basis'),
                'line_discount_rate' => (string) $line->line_discount_rate,
                'tax_code' => (string) $line->tax_code,
                'tax_rate' => (string) $line->tax_rate,
                'tax_zero_reason_code' => $line->tax_zero_reason_code,
                'net_total' => (string) $line->net_total,
                'tax_total' => (string) $line->tax_total,
                'gross_total' => (string) $line->gross_total,
                'warehouse_id' => (int) $line->warehouse_id,
                'location_id' => (int) $line->location_id,
            ];
        })->values()->all();

        $payload = json_encode([
            'renderer_version' => self::RENDERER_VERSION,
            'company_name' => (string) $company->name,
            'invoice_id' => (int) $invoice->getKey(),
            'number' => (string) $invoice->number,
            'mode' => (string) $invoice->getRawOriginal('mode'),
            'invoice_date' => (string) $invoice->getRawOriginal('invoice_date'),
            'currency_code' => (string) $invoice->currency_code,
            'document_discount_rate' => (string) $invoice->document_discount_rate,
            'net_total' => (string) $invoice->net_total,
            'tax_total' => (string) $invoice->tax_total,
            'gross_total' => (string) $invoice->gross_total,
            'customer_legal_name' => (string) $invoice->customer_legal_name,
            'customer_trade_name' => $invoice->customer_trade_name,
            'customer_tax_identity_type' => (string) $invoice->customer_tax_identity_type,
            'customer_tax_number' => $invoice->customer_tax_number,
            'customer_tax_office' => $invoice->customer_tax_office,
            'recipient_name' => $invoice->recipient_name,
            'address_line1' => (string) $invoice->address_line1,
            'address_line2' => $invoice->address_line2,
            'district' => $invoice->district,
            'city' => (string) $invoice->city,
            'postal_code' => $invoice->postal_code,
            'country_code' => (string) $invoice->country_code,
            'note' => $invoice->note,
            'finalized_at' => $finalizedAt->format('c'),
            'lines' => $lines,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $payload);
    }

    private function fileName(string $invoiceNumber): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $invoiceNumber);
        $safe = is_string($safe) ? trim($safe, '-_.') : '';
        if ($safe === '') {
            $safe = 'sales-invoice';
        }

        return $safe.'.pdf';
    }
}
