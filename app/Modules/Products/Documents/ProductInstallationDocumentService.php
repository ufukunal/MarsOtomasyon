<?php

namespace App\Modules\Products\Documents;

use App\Foundation\Clock\Clock;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\FileAsset;
use App\Modules\Products\Enums\ProductFileKind;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductFile;
use App\Modules\Products\Models\ProductInstallationDocument;
use App\Modules\Products\Models\ProductInstallationGuide;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final readonly class ProductInstallationDocumentService
{
    public const RENDERER_VERSION = 'product-installation-pdf.v1';

    public function __construct(
        private ActiveCompanyContext $companyContext,
        private Clock $clock,
    ) {}

    /**
     * @param list<string> $steps
     * @param list<string> $warnings
     * @param list<string> $tools
     * @param list<string> $parts
     * @param list<int> $imageIds
     */
    public function saveDraft(
        int $productId,
        string $title,
        ?string $intro,
        array $steps,
        array $warnings,
        array $tools,
        array $parts,
        array $imageIds,
    ): ProductInstallationGuide {
        $product = $this->product($productId);
        $this->activeImages($product, $imageIds);

        return DB::transaction(function () use ($product, $title, $intro, $steps, $warnings, $tools, $parts, $imageIds): ProductInstallationGuide {
            $guide = ProductInstallationGuide::query()
                ->where('company_id', $this->companyId())
                ->where('product_id', $product->getKey())
                ->lockForUpdate()
                ->first();

            $payload = [
                'company_id' => $this->companyId(),
                'product_id' => $product->getKey(),
                'title' => trim($title),
                'intro' => $intro !== null && trim($intro) !== '' ? trim($intro) : null,
                'steps' => $steps,
                'warnings' => $warnings,
                'tools' => $tools,
                'parts' => $parts,
                'image_product_file_ids' => $imageIds,
            ];

            if ($guide === null) {
                $payload['content_revision'] = 1;

                return ProductInstallationGuide::query()->create($payload);
            }

            $payload['content_revision'] = ((int) $guide->content_revision) + 1;
            $guide->update($payload);

            return $guide->refresh();
        });
    }

    public function guide(int $productId): ?ProductInstallationGuide
    {
        $product = $this->product($productId);

        return ProductInstallationGuide::query()
            ->where('company_id', $this->companyId())
            ->where('product_id', $product->getKey())
            ->first();
    }

    /** @return Collection<int, ProductInstallationDocument> */
    public function documents(int $productId): Collection
    {
        $product = $this->product($productId);

        return ProductInstallationDocument::query()
            ->where('company_id', $this->companyId())
            ->where('product_id', $product->getKey())
            ->with('fileAsset')
            ->orderByDesc('version')
            ->get();
    }

    /** @return Collection<int, ProductFile> */
    public function availableImages(int $productId): Collection
    {
        $product = $this->product($productId);

        return ProductFile::query()
            ->where('company_id', $this->companyId())
            ->where('product_id', $product->getKey())
            ->where('kind', ProductFileKind::Media->value)
            ->whereHas('attachment', static fn ($query) => $query
                ->whereNull('detached_at')
                ->whereHas('fileAsset', static fn ($asset) => $asset
                    ->whereNull('archived_at')
                    ->whereNull('quarantined_at')))
            ->with('attachment.fileAsset')
            ->orderByDesc('is_main')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /** @return array<string, mixed> */
    public function previewPayload(int $productId): array
    {
        $product = $this->product($productId);
        $guide = $this->requiredGuide($product);
        $snapshot = $this->snapshot($product, $guide);

        return $this->renderPayload($snapshot);
    }

    public function publish(int $productId): ProductInstallationDocument
    {
        $companyId = $this->companyId();
        $storedKey = null;

        try {
            return DB::transaction(function () use ($productId, $companyId, &$storedKey): ProductInstallationDocument {
                $product = Product::query()
                    ->where('company_id', $companyId)
                    ->whereKey($productId)
                    ->lockForUpdate()
                    ->firstOrFail();
                $guide = ProductInstallationGuide::query()
                    ->where('company_id', $companyId)
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($guide->steps === []) {
                    throw ValidationException::withMessages(['steps_text' => 'PDF yayınlamak için en az bir kurulum adımı gerekir.']);
                }

                $snapshot = $this->snapshot($product, $guide);
                $sourceFingerprint = $this->sourceFingerprint($snapshot);
                $existing = ProductInstallationDocument::query()
                    ->where('company_id', $companyId)
                    ->where('product_id', $productId)
                    ->where('renderer_version', self::RENDERER_VERSION)
                    ->where('source_fingerprint', $sourceFingerprint)
                    ->with('fileAsset')
                    ->first();
                if ($existing !== null) {
                    return $existing;
                }

                $version = ((int) ProductInstallationDocument::query()
                    ->where('company_id', $companyId)
                    ->where('product_id', $productId)
                    ->max('version')) + 1;
                $pdfBytes = $this->renderPdf($snapshot, $sourceFingerprint, $version);
                $pdfSha256 = hash('sha256', $pdfBytes);
                $storedKey = sprintf(
                    'companies/%d/generated/products/%d/installations/v%d-%s-%s.pdf',
                    $companyId,
                    $productId,
                    $version,
                    self::RENDERER_VERSION,
                    $pdfSha256,
                );
                if (! Storage::disk('local')->put($storedKey, $pdfBytes)) {
                    throw new LogicException('Installation PDF could not be persisted to private storage.');
                }

                $asset = FileAsset::query()->create([
                    'company_id' => $companyId,
                    'uploaded_by_user_id' => null,
                    'storage_disk' => 'local',
                    'storage_key' => $storedKey,
                    'original_name' => $this->fileName($product, $version),
                    'mime_type' => 'application/pdf',
                    'client_extension' => 'pdf',
                    'size_bytes' => strlen($pdfBytes),
                    'sha256' => $pdfSha256,
                ]);
                $assetId = $asset->getKey();
                $guideId = $guide->getKey();
                if (! is_int($assetId) || ! is_int($guideId)) {
                    throw new LogicException('Installation PDF persistence requires integer keys.');
                }

                $document = ProductInstallationDocument::query()->create([
                    'company_id' => $companyId,
                    'product_id' => $productId,
                    'guide_id' => $guideId,
                    'file_asset_id' => $assetId,
                    'version' => $version,
                    'renderer_version' => self::RENDERER_VERSION,
                    'snapshot' => $snapshot,
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

    public function document(int $productId, int $version): ProductInstallationDocument
    {
        $product = $this->product($productId);

        return ProductInstallationDocument::query()
            ->where('company_id', $this->companyId())
            ->where('product_id', $product->getKey())
            ->where('version', $version)
            ->with('fileAsset')
            ->firstOrFail();
    }

    public function verifiedBytes(ProductInstallationDocument $document): string
    {
        if ((int) $document->company_id !== $this->companyId()) {
            throw new LogicException('Installation PDF belongs to another company.');
        }

        $asset = $document->relationLoaded('fileAsset') ? $document->fileAsset : $document->fileAsset()->first();
        abort_unless($asset instanceof FileAsset && $asset->archived_at === null, 410, 'Installation PDF metadata is unavailable.');
        abort_unless($asset->storage_disk === 'local', 410, 'Installation PDF storage contract is invalid.');
        abort_unless(Storage::disk('local')->exists((string) $asset->storage_key), 410, 'Installation PDF storage object is missing.');
        $bytes = Storage::disk('local')->get((string) $asset->storage_key);
        abort_unless(is_string($bytes) && str_starts_with($bytes, '%PDF-'), 410, 'Installation PDF signature is invalid.');
        $sha256 = hash('sha256', $bytes);
        abort_unless(
            hash_equals((string) $document->pdf_sha256, $sha256) && hash_equals((string) $asset->sha256, $sha256),
            410,
            'Installation PDF integrity check failed.',
        );

        return $bytes;
    }

    private function product(int $productId): Product
    {
        return Product::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($productId);
    }

    private function requiredGuide(Product $product): ProductInstallationGuide
    {
        return ProductInstallationGuide::query()
            ->where('company_id', $this->companyId())
            ->where('product_id', $product->getKey())
            ->firstOrFail();
    }

    /**
     * @param list<int> $imageIds
     * @return Collection<int, ProductFile>
     */
    private function activeImages(Product $product, array $imageIds): Collection
    {
        if ($imageIds === []) {
            return new Collection;
        }

        $files = ProductFile::query()
            ->where('company_id', $this->companyId())
            ->where('product_id', $product->getKey())
            ->where('kind', ProductFileKind::Media->value)
            ->whereIn('id', $imageIds)
            ->whereHas('attachment', static fn ($query) => $query
                ->whereNull('detached_at')
                ->whereHas('fileAsset', static fn ($asset) => $asset
                    ->whereNull('archived_at')
                    ->whereNull('quarantined_at')))
            ->with('attachment.fileAsset')
            ->get()
            ->keyBy(static fn (ProductFile $file): int => (int) $file->getKey());

        $ordered = new Collection;
        foreach ($imageIds as $imageId) {
            $file = $files->get($imageId);
            if (! $file instanceof ProductFile) {
                throw ValidationException::withMessages([
                    'image_ids' => 'Kurulum PDF görselleri aynı ürüne ait, aktif ve karantinada olmayan medya dosyaları olmalıdır.',
                ]);
            }
            $ordered->push($file);
        }

        return $ordered;
    }

    /** @return array<string, mixed> */
    private function snapshot(Product $product, ProductInstallationGuide $guide): array
    {
        $imageIds = array_values(array_map(static fn (mixed $id): int => (int) $id, $guide->image_product_file_ids));
        $images = $this->activeImages($product, $imageIds);
        $imageSnapshot = [];
        foreach ($images as $file) {
            $asset = $file->attachment?->fileAsset;
            if (! $asset instanceof FileAsset) {
                throw new LogicException('Installation image requires an active private file asset.');
            }
            $imageSnapshot[] = [
                'product_file_id' => (int) $file->getKey(),
                'file_asset_id' => (int) $asset->getKey(),
                'original_name' => (string) $asset->original_name,
                'mime_type' => (string) $asset->mime_type,
                'sha256' => (string) $asset->sha256,
            ];
        }

        return [
            'product' => [
                'id' => (int) $product->getKey(),
                'code' => (string) $product->code,
                'name' => (string) $product->name,
                'brand' => $product->brand !== null ? (string) $product->brand : null,
            ],
            'guide' => [
                'id' => (int) $guide->getKey(),
                'content_revision' => (int) $guide->content_revision,
                'title' => (string) $guide->title,
                'intro' => $guide->intro !== null ? (string) $guide->intro : null,
                'steps' => array_values($guide->steps),
                'warnings' => array_values($guide->warnings),
                'tools' => array_values($guide->tools),
                'parts' => array_values($guide->parts),
                'images' => $imageSnapshot,
            ],
        ];
    }

    /** @param array<string, mixed> $snapshot */
    private function sourceFingerprint(array $snapshot): string
    {
        $encoded = json_encode(
            ['renderer_version' => self::RENDERER_VERSION, 'snapshot' => $snapshot],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return hash('sha256', $encoded);
    }

    /** @param array<string, mixed> $snapshot */
    private function renderPdf(array $snapshot, string $sourceFingerprint, int $version): string
    {
        $html = view('products.installation.document', [
            ...$this->renderPayload($snapshot),
            'version' => $version,
            'rendererVersion' => self::RENDERER_VERSION,
            'sourceFingerprint' => $sourceFingerprint,
            'isPreview' => false,
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
            throw new LogicException('Dompdf did not produce a valid installation PDF payload.');
        }

        return $output;
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    private function renderPayload(array $snapshot): array
    {
        $guide = is_array($snapshot['guide'] ?? null) ? $snapshot['guide'] : [];
        $images = is_array($guide['images'] ?? null) ? $guide['images'] : [];
        $renderImages = [];
        foreach ($images as $image) {
            if (! is_array($image)) {
                continue;
            }
            $assetId = (int) ($image['file_asset_id'] ?? 0);
            $asset = FileAsset::query()
                ->where('company_id', $this->companyId())
                ->whereKey($assetId)
                ->whereNull('archived_at')
                ->whereNull('quarantined_at')
                ->first();
            if (! $asset instanceof FileAsset || ! str_starts_with((string) $asset->mime_type, 'image/')) {
                throw new LogicException('Installation PDF image is no longer renderable.');
            }
            abort_unless($asset->storage_disk === 'local' && Storage::disk('local')->exists((string) $asset->storage_key), 410, 'Installation image storage object is missing.');
            $bytes = Storage::disk('local')->get((string) $asset->storage_key);
            if (! is_string($bytes) || ! hash_equals((string) $image['sha256'], hash('sha256', $bytes))) {
                abort(410, 'Installation image integrity check failed.');
            }
            $renderImages[] = [
                ...$image,
                'data_uri' => 'data:'.(string) $asset->mime_type.';base64,'.base64_encode($bytes),
            ];
        }
        $guide['images'] = $renderImages;

        return [
            'productData' => is_array($snapshot['product'] ?? null) ? $snapshot['product'] : [],
            'guideData' => $guide,
        ];
    }

    private function fileName(Product $product, int $version): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $product->code);
        $safe = is_string($safe) ? trim($safe, '-_.') : '';

        return ($safe !== '' ? $safe : 'product').'-installation-v'.$version.'.pdf';
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();

        return is_int($id) ? $id : throw new LogicException('Installation PDF operation requires a persisted company.');
    }
}