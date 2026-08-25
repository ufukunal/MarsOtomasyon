<?php

namespace App\Modules\Quotes\Documents;

use App\Foundation\Clock\Clock;
use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Models\FileAsset;
use App\Modules\Quotes\Enums\QuoteStatus;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuoteFinalizedDocument;
use App\Modules\Quotes\Models\QuoteRevision;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Throwable;

final readonly class QuoteFinalizedDocumentService
{
    public const RENDERER_VERSION = 'quote-pdf.v1';

    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
        private Clock $clock,
    ) {}

    public function getOrCreate(int $quoteId): QuoteFinalizedDocument
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $storedKey = null;

        try {
            return DB::transaction(function () use ($companyId, $quoteId, &$storedKey): QuoteFinalizedDocument {
                $quote = Quote::query()
                    ->where('company_id', $companyId)
                    ->whereKey($quoteId)
                    ->lockForUpdate()
                    ->with(['company', 'selectedRevision.lines'])
                    ->firstOrFail();

                $revision = $quote->selectedRevision;
                if (! $revision instanceof QuoteRevision || ! $this->isFinalized($quote->statusEnum())) {
                    throw new LogicException('Finalized PDF requires an approved, rejected, or converted quote with a selected revision.');
                }

                $existing = QuoteFinalizedDocument::query()
                    ->where('company_id', $companyId)
                    ->where('quote_id', $quoteId)
                    ->where('renderer_version', self::RENDERER_VERSION)
                    ->lockForUpdate()
                    ->with('fileAsset')
                    ->first();

                if ($existing !== null) {
                    if ((int) $existing->quote_revision_id !== (int) $revision->getKey()) {
                        throw new LogicException('Persisted finalized PDF revision does not match the selected quote revision.');
                    }

                    return $existing;
                }

                $decisionOutcome = $quote->statusEnum() === QuoteStatus::Rejected ? 'rejected' : 'approved';
                $sourceFingerprint = $this->sourceFingerprint($quote, $revision, $decisionOutcome);
                $pdfBytes = $this->render($quote, $revision, $decisionOutcome, $sourceFingerprint);
                $pdfSha256 = hash('sha256', $pdfBytes);
                $originalName = $this->fileName((string) $revision->quote_number, (int) $revision->revision_number);
                $storedKey = sprintf(
                    'companies/%d/generated/quotes/%d/%s-%s.pdf',
                    $companyId,
                    $quoteId,
                    self::RENDERER_VERSION,
                    $pdfSha256,
                );

                if (! Storage::disk('local')->put($storedKey, $pdfBytes)) {
                    throw new LogicException('Finalized PDF could not be persisted to private storage.');
                }

                $asset = FileAsset::query()->create([
                    'company_id' => $companyId,
                    'uploaded_by_user_id' => null,
                    'storage_disk' => 'local',
                    'storage_key' => $storedKey,
                    'original_name' => $originalName,
                    'mime_type' => 'application/pdf',
                    'client_extension' => 'pdf',
                    'size_bytes' => strlen($pdfBytes),
                    'sha256' => $pdfSha256,
                ]);

                $assetId = $asset->getKey();
                if (! is_int($assetId)) {
                    throw new LogicException('Finalized PDF file asset persistence did not return an integer key.');
                }

                $document = QuoteFinalizedDocument::query()->create([
                    'company_id' => $companyId,
                    'quote_id' => $quoteId,
                    'quote_revision_id' => $revision->getKey(),
                    'file_asset_id' => $assetId,
                    'renderer_version' => self::RENDERER_VERSION,
                    'source_fingerprint' => $sourceFingerprint,
                    'pdf_sha256' => $pdfSha256,
                    'generated_at' => $this->clock->now(),
                ]);

                $documentId = $document->getKey();
                if (! is_int($documentId)) {
                    throw new LogicException('Finalized quote document persistence did not return an integer key.');
                }

                $this->audit->record(
                    AuditAction::QuoteFinalizedPdfGenerated,
                    AuditTargetType::Quote,
                    $quoteId,
                    after: [
                        'quote_finalized_document_id' => $documentId,
                        'quote_revision_id' => (int) $revision->getKey(),
                        'renderer_version' => self::RENDERER_VERSION,
                        'source_fingerprint' => $sourceFingerprint,
                        'pdf_sha256' => $pdfSha256,
                        'size_bytes' => strlen($pdfBytes),
                    ],
                );

                return $document->setRelation('fileAsset', $asset);
            });
        } catch (Throwable $exception) {
            if (is_string($storedKey) && $storedKey !== '') {
                Storage::disk('local')->delete($storedKey);
            }

            throw $exception;
        }
    }

    public function verifiedBytes(QuoteFinalizedDocument $document): string
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        if ((int) $document->company_id !== $companyId) {
            throw new LogicException('Finalized PDF belongs to another company.');
        }

        $asset = $document->relationLoaded('fileAsset') ? $document->fileAsset : $document->fileAsset()->first();
        abort_unless($asset instanceof FileAsset && $asset->archived_at === null, 410, 'Finalized PDF file metadata is unavailable.');
        abort_unless($asset->storage_disk === 'local', 410, 'Finalized PDF storage contract is invalid.');
        abort_unless(Storage::disk('local')->exists((string) $asset->storage_key), 410, 'Finalized PDF storage object is missing.');

        $bytes = Storage::disk('local')->get((string) $asset->storage_key);
        abort_unless(str_starts_with($bytes, '%PDF-'), 410, 'Finalized PDF signature is invalid.');

        $sha256 = hash('sha256', $bytes);
        abort_unless(
            hash_equals((string) $document->pdf_sha256, $sha256)
            && hash_equals((string) $asset->sha256, $sha256),
            410,
            'Finalized PDF integrity check failed.',
        );

        return $bytes;
    }

    private function render(Quote $quote, QuoteRevision $revision, string $decisionOutcome, string $sourceFingerprint): string
    {
        $html = view('quotes.pdf.finalized', [
            'quote' => $quote,
            'revision' => $revision,
            'decisionOutcome' => $decisionOutcome,
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
            throw new LogicException('Dompdf did not produce a valid PDF payload.');
        }

        return $output;
    }

    private function sourceFingerprint(Quote $quote, QuoteRevision $revision, string $decisionOutcome): string
    {
        $payload = json_encode([
            'renderer_version' => self::RENDERER_VERSION,
            'quote_revision_fingerprint' => (string) $revision->content_fingerprint,
            'quote_revision_id' => (int) $revision->getKey(),
            'company_name' => (string) $quote->company->name,
            'decision_outcome' => $decisionOutcome,
            'decision_at' => $quote->decision_at?->format('c'),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $payload);
    }

    private function fileName(string $quoteNumber, int $revisionNumber): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $quoteNumber);
        $safe = is_string($safe) ? trim($safe, '-_.') : '';
        if ($safe === '') {
            $safe = 'quote';
        }

        return $safe.'-R'.$revisionNumber.'.pdf';
    }

    private function isFinalized(QuoteStatus $status): bool
    {
        return in_array($status, [QuoteStatus::Approved, QuoteStatus::Rejected, QuoteStatus::Converted], true);
    }
}
