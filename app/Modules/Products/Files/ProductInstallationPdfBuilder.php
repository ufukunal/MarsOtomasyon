<?php

namespace App\Modules\Products\Files;

use App\Foundation\Clock\Clock;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Files\PrivateAttachmentManager;
use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\FileAsset;
use App\Modules\Products\Enums\ProductFileKind;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductFile;
use App\Modules\Products\Models\ProductInstallationGuide;
use App\Modules\Products\Models\ProductInstallationGuideVersion;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final readonly class ProductInstallationPdfBuilder
{
    private const MAX_STEPS = 100;

    private const MAX_WARNINGS = 50;

    private const MAX_TOOLS = 50;

    private const MAX_PARTS = 100;

    private const MAX_IMAGES = 20;

    private const MAX_EMBEDDED_IMAGE_BYTES = 20_971_520;

    public function __construct(
        private ActiveCompanyContext $companyContext,
        private PrivateAttachmentManager $attachments,
        private Clock $clock,
    ) {}

    /** @param array<string, mixed> $draft */
    public function preview(int $productId, array $draft, ?string $title = null): string
    {
        $product = $this->product($productId);
        $content = $this->normalizeContent($product, $draft);

        return $this->renderHtml($product, $this->normalizeTitle($product, $title), $content);
    }

    /** @param array<string, mixed> $draft */
    public function publish(int $productId, array $draft, ?string $title = null): ProductInstallationGuideVersion
    {
        $product = $this->product($productId);
        $normalizedTitle = $this->normalizeTitle($product, $title);
        $content = $this->normalizeContent($product, $draft);
        $pdf = $this->renderPdf($this->renderHtml($product, $normalizedTitle, $content));
        $attachment = $this->storePdf($product, $normalizedTitle, $pdf);
        $attachmentId = $attachment->getKey();
        if (! is_int($attachmentId)) {
            throw new LogicException('Generated installation PDF attachment must be persisted.');
        }

        try {
            return DB::transaction(function () use ($product, $normalizedTitle, $content, $attachmentId): ProductInstallationGuideVersion {
                $companyId = $this->companyId();
                Product::query()
                    ->where('company_id', $companyId)
                    ->whereKey($product->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $guide = ProductInstallationGuide::query()->firstOrCreate(
                    ['company_id' => $companyId, 'product_id' => $product->getKey()],
                    ['title' => $normalizedTitle],
                );
                $guide = ProductInstallationGuide::query()
                    ->where('company_id', $companyId)
                    ->whereKey($guide->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($guide->title !== $normalizedTitle) {
                    $guide->update(['title' => $normalizedTitle]);
                }

                $latestVersion = ProductInstallationGuideVersion::query()
                    ->where('company_id', $companyId)
                    ->where('guide_id', $guide->getKey())
                    ->max('version_no');
                $versionNo = is_numeric($latestVersion) ? ((int) $latestVersion) + 1 : 1;

                return ProductInstallationGuideVersion::query()->create([
                    'company_id' => $companyId,
                    'guide_id' => $guide->getKey(),
                    'version_no' => $versionNo,
                    'content' => $content,
                    'pdf_attachment_id' => $attachmentId,
                    'generated_by_user_id' => $this->actorId(),
                    'generated_at' => $this->clock->now(),
                ])->load(['guide', 'pdfAttachment.fileAsset', 'generatedBy']);
            });
        } catch (Throwable $exception) {
            $this->attachments->detach(AttachmentTargetType::Product, (int) $product->getKey(), $attachmentId);

            throw $exception;
        }
    }

    public function download(int $productId, int $versionId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $product = $this->product($productId);
        $version = ProductInstallationGuideVersion::query()
            ->where('company_id', $this->companyId())
            ->whereHas('guide', static fn ($query) => $query->where('product_id', $product->getKey()))
            ->findOrFail($versionId);
        if ($version->pdf_attachment_id === null) {
            abort(404);
        }

        return $this->attachments->download(
            AttachmentTargetType::Product,
            (int) $product->getKey(),
            (int) $version->pdf_attachment_id,
        );
    }

    /** @param array<string, mixed> $draft @return array<string, mixed> */
    private function normalizeContent(Product $product, array $draft): array
    {
        $steps = $draft['steps'] ?? null;
        if (! is_array($steps) || $steps === [] || count($steps) > self::MAX_STEPS) {
            throw ValidationException::withMessages(['steps' => 'Kurulum kılavuzu 1-'.self::MAX_STEPS.' adım içermelidir.']);
        }

        $normalizedSteps = [];
        $referencedImageIds = [];
        foreach (array_values($steps) as $index => $step) {
            if (! is_array($step)) {
                throw ValidationException::withMessages(['steps.'.$index => 'Kurulum adımı nesne olmalıdır.']);
            }
            $stepTitle = $this->requiredText($step['title'] ?? null, 180, 'steps.'.$index.'.title');
            $body = $this->requiredText($step['body'] ?? null, 4000, 'steps.'.$index.'.body');
            $imageId = $step['image_product_file_id'] ?? null;
            if ($imageId !== null && (! is_int($imageId) || $imageId < 1)) {
                throw ValidationException::withMessages(['steps.'.$index.'.image_product_file_id' => 'Adım görsel kimliği pozitif tam sayı olmalıdır.']);
            }
            if (is_int($imageId)) {
                $referencedImageIds[$imageId] = true;
            }
            $normalizedSteps[] = [
                'title' => $stepTitle,
                'body' => $body,
                'image_product_file_id' => $imageId,
            ];
        }

        $warnings = $this->textList($draft['warnings'] ?? [], self::MAX_WARNINGS, 500, 'warnings');
        $tools = $this->textList($draft['tools'] ?? [], self::MAX_TOOLS, 180, 'tools');
        $parts = $this->parts($draft['parts'] ?? []);

        $images = $draft['images'] ?? [];
        if (! is_array($images) || count($images) > self::MAX_IMAGES) {
            throw ValidationException::withMessages(['images' => 'Görsel listesi en fazla '.self::MAX_IMAGES.' kayıt içerebilir.']);
        }
        $normalizedImages = [];
        foreach ($images as $index => $imageId) {
            if (! is_int($imageId) || $imageId < 1) {
                throw ValidationException::withMessages(['images.'.$index => 'Görsel kimliği pozitif tam sayı olmalıdır.']);
            }
            $normalizedImages[$imageId] = true;
            $referencedImageIds[$imageId] = true;
        }
        $normalizedImageIds = array_keys($normalizedImages);
        if (count($normalizedImageIds) > self::MAX_IMAGES) {
            throw ValidationException::withMessages(['images' => 'Görsel listesi en fazla '.self::MAX_IMAGES.' benzersiz kayıt içerebilir.']);
        }

        $this->validatedMediaMap($product, array_keys($referencedImageIds));

        return [
            'steps' => $normalizedSteps,
            'warnings' => $warnings,
            'tools' => $tools,
            'parts' => $parts,
            'images' => $normalizedImageIds,
        ];
    }

    /** @param array<string, mixed> $content */
    private function renderHtml(Product $product, string $title, array $content): string
    {
        $imageIds = [];
        foreach ($content['steps'] as $step) {
            if (is_int($step['image_product_file_id'])) {
                $imageIds[$step['image_product_file_id']] = true;
            }
        }
        foreach ($content['images'] as $imageId) {
            $imageIds[$imageId] = true;
        }
        $media = $this->validatedMediaMap($product, array_keys($imageIds));

        $html = '<!doctype html><html lang="tr"><head><meta charset="utf-8"><style>'
            .'@page { size: A4; margin: 16mm 15mm; }'
            .'body{font-family:DejaVu Sans,sans-serif;color:#111;font-size:10.5pt;line-height:1.45;}'
            .'h1{font-size:20pt;margin:0 0 5mm;}h2{font-size:13pt;margin:6mm 0 2mm;}'
            .'.meta{color:#555;margin-bottom:6mm}.warning{border:1px solid #777;padding:3mm;margin:2mm 0;}'
            .'.step{page-break-inside:avoid;margin:0 0 6mm}.step img,.gallery img{max-width:100%;max-height:95mm;display:block;margin:3mm 0;}'
            .'.gallery-item{page-break-inside:avoid;margin-bottom:5mm}table{width:100%;border-collapse:collapse;}'
            .'td,th{border:1px solid #aaa;padding:2mm;text-align:left;vertical-align:top;}'
            .'</style></head><body>';
        $html .= '<h1>'.$this->escape($title).'</h1>';
        $html .= '<div class="meta">Ürün: '.$this->escape((string) $product->name).' · Kod: '.$this->escape((string) $product->code).'</div>';

        if ($content['warnings'] !== []) {
            $html .= '<h2>Uyarılar</h2>';
            foreach ($content['warnings'] as $warning) {
                $html .= '<div class="warning">'.$this->escape($warning).'</div>';
            }
        }

        if ($content['tools'] !== []) {
            $html .= '<h2>Gerekli araçlar</h2><ul>';
            foreach ($content['tools'] as $tool) {
                $html .= '<li>'.$this->escape($tool).'</li>';
            }
            $html .= '</ul>';
        }

        if ($content['parts'] !== []) {
            $html .= '<h2>Parçalar</h2><table><thead><tr><th>Parça</th><th>Miktar</th></tr></thead><tbody>';
            foreach ($content['parts'] as $part) {
                $html .= '<tr><td>'.$this->escape($part['name']).'</td><td>'.$this->escape($part['quantity']).'</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        $html .= '<h2>Kurulum adımları</h2>';
        foreach ($content['steps'] as $index => $step) {
            $html .= '<section class="step"><h2>'.($index + 1).'. '.$this->escape($step['title']).'</h2>';
            $html .= '<div>'.$this->multiline($step['body']).'</div>';
            $imageId = $step['image_product_file_id'];
            if (is_int($imageId) && isset($media[$imageId])) {
                $html .= '<img alt="'.$this->escape($step['title']).'" src="'.$this->dataUri($media[$imageId]).'">';
            }
            $html .= '</section>';
        }

        if ($content['images'] !== []) {
            $html .= '<h2>Ek görseller</h2><div class="gallery">';
            foreach ($content['images'] as $imageId) {
                if (isset($media[$imageId])) {
                    $html .= '<div class="gallery-item"><img alt="Ürün kurulum görseli" src="'.$this->dataUri($media[$imageId]).'"></div>';
                }
            }
            $html .= '</div>';
        }

        return $html.'</body></html>';
    }

    private function renderPdf(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();
        $pdf = $dompdf->output();
        if ($pdf === '') {
            throw new LogicException('Installation PDF renderer returned an empty document.');
        }

        return $pdf;
    }

    private function storePdf(Product $product, string $title, string $pdf): Attachment
    {
        $temporary = tempnam(sys_get_temp_dir(), 'mars-installation-');
        if (! is_string($temporary)) {
            throw new LogicException('Generated PDF temporary file could not be created.');
        }

        try {
            if (file_put_contents($temporary, $pdf) !== strlen($pdf)) {
                throw new LogicException('Generated PDF temporary file could not be written.');
            }
            $fileName = Str::slug((string) $product->code).'-kurulum.pdf';
            if ($fileName === '-kurulum.pdf') {
                $fileName = 'urun-'.$product->getKey().'-kurulum.pdf';
            }
            $upload = new UploadedFile($temporary, $fileName, 'application/pdf', null, true);

            return $this->attachments->upload(
                AttachmentTargetType::Product,
                (int) $product->getKey(),
                $upload,
                mb_substr('Kurulum PDF · '.$title, 0, 255),
            );
        } finally {
            @unlink($temporary);
        }
    }

    /** @param list<int> $ids @return array<int, ProductFile> */
    private function validatedMediaMap(Product $product, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $uniqueIds = array_values(array_unique(array_map('intval', $ids)));
        if (count($uniqueIds) > (self::MAX_IMAGES + self::MAX_STEPS)) {
            throw ValidationException::withMessages(['images' => 'Referans verilen görsel sayısı sınırı aşıldı.']);
        }

        $files = ProductFile::query()
            ->where('company_id', $this->companyId())
            ->where('product_id', $product->getKey())
            ->where('kind', ProductFileKind::Media->value)
            ->whereIn('id', $uniqueIds)
            ->whereHas('attachment', static fn ($query) => $query->whereNull('detached_at'))
            ->with('attachment.fileAsset')
            ->get()
            ->keyBy('id');
        if ($files->count() !== count($uniqueIds)) {
            throw ValidationException::withMessages(['images' => 'Kurulum PDF görselleri aktif ürün medyası olmalıdır.']);
        }

        $totalBytes = 0;
        $result = [];
        foreach ($uniqueIds as $id) {
            $file = $files->get($id);
            $asset = $file?->attachment?->fileAsset;
            if (! $file instanceof ProductFile || ! $asset instanceof FileAsset) {
                throw ValidationException::withMessages(['images' => 'Kurulum PDF görsel dosyası bulunamadı.']);
            }
            $mime = (string) $asset->mime_type;
            if ($asset->archived_at !== null || $asset->quarantined_at !== null || ! str_starts_with($mime, 'image/')) {
                throw ValidationException::withMessages(['images' => 'Arşivlenmiş, karantinadaki veya görsel olmayan dosya PDF içinde kullanılamaz.']);
            }
            $size = (int) $asset->size_bytes;
            $totalBytes += $size;
            if ($size < 1 || $totalBytes > self::MAX_EMBEDDED_IMAGE_BYTES) {
                throw ValidationException::withMessages(['images' => 'Kurulum PDF görsellerinin toplam boyutu 20 MB sınırını aşamaz.']);
            }
            if (! Storage::disk((string) $asset->storage_disk)->exists((string) $asset->storage_key)) {
                throw ValidationException::withMessages(['images' => 'Kurulum PDF görseli storage alanında bulunamadı.']);
            }
            $result[$id] = $file;
        }

        return $result;
    }

    private function dataUri(ProductFile $file): string
    {
        $asset = $file->attachment?->fileAsset;
        if (! $asset instanceof FileAsset) {
            throw new LogicException('Installation PDF media requires a file asset.');
        }
        $bytes = Storage::disk((string) $asset->storage_disk)->get((string) $asset->storage_key);

        return 'data:'.$this->escape((string) $asset->mime_type).';base64,'.base64_encode($bytes);
    }

    /** @param mixed $value @return list<string> */
    private function textList(mixed $value, int $maxItems, int $maxLength, string $field): array
    {
        if (! is_array($value) || count($value) > $maxItems) {
            throw ValidationException::withMessages([$field => $field.' listesi en fazla '.$maxItems.' kayıt içerebilir.']);
        }
        $normalized = [];
        foreach (array_values($value) as $index => $item) {
            $normalized[] = $this->requiredText($item, $maxLength, $field.'.'.$index);
        }

        return $normalized;
    }

    /** @param mixed $value @return list<array{name:string,quantity:string}> */
    private function parts(mixed $value): array
    {
        if (! is_array($value) || count($value) > self::MAX_PARTS) {
            throw ValidationException::withMessages(['parts' => 'Parça listesi en fazla '.self::MAX_PARTS.' kayıt içerebilir.']);
        }
        $normalized = [];
        foreach (array_values($value) as $index => $part) {
            if (! is_array($part)) {
                throw ValidationException::withMessages(['parts.'.$index => 'Parça kaydı nesne olmalıdır.']);
            }
            $normalized[] = [
                'name' => $this->requiredText($part['name'] ?? null, 180, 'parts.'.$index.'.name'),
                'quantity' => $this->requiredText($part['quantity'] ?? null, 80, 'parts.'.$index.'.quantity'),
            ];
        }

        return $normalized;
    }

    private function requiredText(mixed $value, int $maxLength, string $field): string
    {
        if (! is_string($value)) {
            throw ValidationException::withMessages([$field => $field.' metin olmalıdır.']);
        }
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw ValidationException::withMessages([$field => $field.' 1-'.$maxLength.' karakter arasında olmalıdır.']);
        }

        return $value;
    }

    private function normalizeTitle(Product $product, ?string $title): string
    {
        $value = $title === null ? trim((string) $product->name).' Kurulum Kılavuzu' : trim($title);
        if ($value === '' || mb_strlen($value) > 255) {
            throw ValidationException::withMessages(['title' => 'Kurulum kılavuzu başlığı 1-255 karakter arasında olmalıdır.']);
        }

        return $value;
    }

    private function product(int $productId): Product
    {
        return Product::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($productId);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function multiline(string $value): string
    {
        return nl2br($this->escape($value), false);
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();

        return is_int($id) ? $id : throw new LogicException('Installation PDF operation requires a persisted company.');
    }

    private function actorId(): int
    {
        $id = Auth::id();

        return is_int($id) ? $id : throw new LogicException('Installation PDF generation requires an authenticated actor.');
    }
}
