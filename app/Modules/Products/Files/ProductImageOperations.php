<?php

namespace App\Modules\Products\Files;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Files\PrivateAttachmentManager;
use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\FileAsset;
use App\Modules\Products\Enums\ProductFileKind;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductFile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class ProductImageOperations
{
    private const PROVIDER_STATUSES = ['pending', 'valid', 'warning', 'invalid'];

    public function __construct(
        private ActiveCompanyContext $companyContext,
        private PrivateAttachmentManager $attachments,
        private AuditRecorder $audit,
    ) {}

    public function setMain(int $productId, int $productFileId): ProductFile
    {
        $id = $this->productId($productId);

        return DB::transaction(function () use ($id, $productFileId): ProductFile {
            $file = $this->mediaFile($id, $productFileId, true);
            $currentMain = ProductFile::query()
                ->where('company_id', $this->companyId())
                ->where('product_id', $id)
                ->where('kind', ProductFileKind::Media->value)
                ->where('is_main', true)
                ->whereHas('attachment', static fn ($query) => $query->whereNull('detached_at'))
                ->lockForUpdate()
                ->first();
            $currentMainId = $currentMain?->getKey();
            $nextMainId = $file->getKey();
            if (! is_int($nextMainId)) {
                throw new LogicException('Product media main operation requires a persisted media row.');
            }
            if ($currentMainId === $nextMainId) {
                return $file;
            }

            ProductFile::query()
                ->where('company_id', $this->companyId())
                ->where('product_id', $id)
                ->where('kind', ProductFileKind::Media->value)
                ->where('is_main', true)
                ->where('id', '<>', $nextMainId)
                ->update(['is_main' => false, 'updated_at' => now()]);
            $file->update(['is_main' => true]);

            $this->auditMedia(
                $id,
                'set_main',
                ['main_product_file_id' => is_int($currentMainId) ? $currentMainId : null],
                ['main_product_file_id' => $nextMainId],
            );

            return $file->refresh();
        });
    }

    /**
     * @param  list<int>  $orderedProductFileIds
     * @return Collection<int, ProductFile>
     */
    public function reorder(int $productId, array $orderedProductFileIds): Collection
    {
        $id = $this->productId($productId);
        $ordered = array_map('intval', $orderedProductFileIds);
        if ($ordered === [] || count($ordered) !== count(array_unique($ordered)) || min($ordered) < 1) {
            throw ValidationException::withMessages(['order' => 'Medya sırası benzersiz ve pozitif dosya kimlikleri içermelidir.']);
        }
        if (count($ordered) > 32768) {
            throw ValidationException::withMessages(['order' => 'Medya sıralama sınırı aşıldı.']);
        }

        DB::transaction(function () use ($id, $ordered): void {
            $beforeOrder = ProductFile::query()
                ->where('company_id', $this->companyId())
                ->where('product_id', $id)
                ->where('kind', ProductFileKind::Media->value)
                ->whereHas('attachment', static fn ($query) => $query->whereNull('detached_at'))
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->map(static fn ($value): int => (int) $value)
                ->values()
                ->all();
            $activeIds = $beforeOrder;
            sort($activeIds);
            $expected = $ordered;
            sort($expected);
            if ($expected !== $activeIds) {
                throw ValidationException::withMessages(['order' => 'Sıralama aktif medya kümesiyle birebir eşleşmelidir.']);
            }
            if ($beforeOrder === $ordered) {
                return;
            }

            foreach ($ordered as $position => $fileId) {
                ProductFile::query()
                    ->where('company_id', $this->companyId())
                    ->where('product_id', $id)
                    ->whereKey($fileId)
                    ->update(['position' => $position, 'updated_at' => now()]);
            }

            $this->auditMedia(
                $id,
                'reorder',
                ['product_file_ids' => $beforeOrder],
                ['product_file_ids' => $ordered],
            );
        });

        return $this->media($id);
    }

    /** @param list<string> $destinations */
    public function updateDestinations(int $productId, int $productFileId, array $destinations): ProductFile
    {
        $id = $this->productId($productId);
        $normalized = [];
        foreach ($destinations as $destination) {
            $value = trim($destination);
            if ($value === '' || mb_strlen($value) > 128) {
                throw ValidationException::withMessages(['destinations' => 'Hedef kimliği 1-128 karakter arasında olmalıdır.']);
            }
            $normalized[$value] = true;
        }
        if (count($normalized) > 32) {
            throw ValidationException::withMessages(['destinations' => 'Bir görsel en fazla 32 hedefe bağlanabilir.']);
        }
        $values = array_keys($normalized);
        sort($values);

        return DB::transaction(function () use ($id, $productFileId, $values): ProductFile {
            $file = $this->mediaFile($id, $productFileId, true);
            $before = is_array($file->destinations) ? $file->destinations : [];
            if ($before === $values) {
                return $file;
            }
            $file->update(['destinations' => $values]);
            $this->auditMedia(
                $id,
                'destinations',
                ['product_file_id' => $productFileId, 'destinations' => $before],
                ['product_file_id' => $productFileId, 'destinations' => $values],
            );

            return $file->refresh();
        });
    }

    /** @param array<string, mixed> $metadata */
    public function updateTransformMetadata(int $productId, int $productFileId, array $metadata): ProductFile
    {
        $id = $this->productId($productId);
        $normalized = $this->normalizeTransform($metadata);

        return DB::transaction(function () use ($id, $productFileId, $normalized): ProductFile {
            $file = $this->mediaFile($id, $productFileId, true);
            $before = is_array($file->transform_metadata) ? $file->transform_metadata : [];
            if ($before === $normalized) {
                return $file;
            }
            $file->update(['transform_metadata' => $normalized]);
            $this->auditMedia(
                $id,
                'transform',
                ['product_file_id' => $productFileId, 'transform' => $before],
                ['product_file_id' => $productFileId, 'transform' => $normalized],
            );

            return $file->refresh();
        });
    }

    /**
     * @param  list<string>  $messages
     * @param  array<string, mixed>  $details
     */
    public function recordProviderValidation(
        int $productId,
        int $productFileId,
        string $provider,
        string $status,
        array $messages = [],
        array $details = [],
    ): ProductFile {
        $id = $this->productId($productId);
        $provider = trim($provider);
        $status = mb_strtolower(trim($status));
        if ($provider === '' || mb_strlen($provider) > 80) {
            throw ValidationException::withMessages(['provider' => 'Provider kimliği 1-80 karakter arasında olmalıdır.']);
        }
        if (! in_array($status, self::PROVIDER_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Provider doğrulama durumu geçersiz.']);
        }
        $normalizedMessages = [];
        foreach ($messages as $message) {
            if (trim($message) === '') {
                throw ValidationException::withMessages(['messages' => 'Provider doğrulama mesajları boş olmayan metinlerden oluşmalıdır.']);
            }
            $normalizedMessages[] = mb_substr(trim($message), 0, 500);
        }
        if (count($normalizedMessages) > 20) {
            throw ValidationException::withMessages(['messages' => 'En fazla 20 provider doğrulama mesajı kaydedilebilir.']);
        }
        $next = [
            'provider' => $provider,
            'status' => $status,
            'messages' => $normalizedMessages,
            'details' => $details,
            'validated_at' => now()->toIso8601String(),
        ];

        return DB::transaction(function () use ($id, $productFileId, $next): ProductFile {
            $file = $this->mediaFile($id, $productFileId, true);
            $before = is_array($file->provider_validation) ? $file->provider_validation : [];
            $file->update(['provider_validation' => $next]);
            $this->auditMedia(
                $id,
                'provider_validation',
                ['product_file_id' => $productFileId, 'provider_validation' => $before],
                ['product_file_id' => $productFileId, 'provider_validation' => $next],
            );

            return $file->refresh();
        });
    }

    public function copy(int $sourceProductId, int $productFileId, int $targetProductId): ProductFile
    {
        $sourceId = $this->productId($sourceProductId);
        $targetId = $this->productId($targetProductId);
        if ($sourceId === $targetId) {
            throw ValidationException::withMessages(['target_product_id' => 'Görsel aynı ürüne kopyalanamaz.']);
        }

        return DB::transaction(function () use ($sourceId, $productFileId, $targetId): ProductFile {
            $source = $this->mediaFile($sourceId, $productFileId, true);
            $attachment = $source->attachment;
            $asset = $attachment?->fileAsset;
            if (! $attachment instanceof Attachment || ! $asset instanceof FileAsset) {
                throw new LogicException('Product media requires an active attachment and file asset.');
            }
            $assetId = $asset->getKey();
            if (! is_int($assetId)) {
                throw new LogicException('Product media copy requires a persisted file asset.');
            }

            $linked = $this->attachments->linkExistingAsset(
                AttachmentTargetType::Product,
                $targetId,
                $assetId,
                $attachment->label,
            );
            $linkedId = $linked->getKey();
            if (! is_int($linkedId)) {
                throw new LogicException('Copied product attachment persistence did not return an integer key.');
            }

            $copy = ProductFile::query()->create([
                'company_id' => $this->companyId(),
                'product_id' => $targetId,
                'attachment_id' => $linkedId,
                'kind' => ProductFileKind::Media,
                'position' => $this->nextPosition($targetId),
                'is_main' => ! $this->hasActiveMain($targetId),
                'destinations' => $source->destinations,
                'transform_metadata' => $source->transform_metadata,
                'provider_validation' => $source->provider_validation,
            ]);
            $copyId = $copy->getKey();
            if (! is_int($copyId)) {
                throw new LogicException('Copied product media persistence did not return an integer key.');
            }

            $this->auditMedia(
                $targetId,
                'copy_in',
                null,
                [
                    'product_file_id' => $copyId,
                    'source_product_id' => $sourceId,
                    'source_product_file_id' => $productFileId,
                    'file_asset_id' => $assetId,
                ],
            );

            return $copy->load(['attachment.fileAsset', 'attachment.attachedBy']);
        });
    }

    public function move(int $sourceProductId, int $productFileId, int $targetProductId): ProductFile
    {
        return DB::transaction(function () use ($sourceProductId, $productFileId, $targetProductId): ProductFile {
            $sourceId = $this->productId($sourceProductId);
            $source = $this->mediaFile($sourceId, $productFileId, true);
            $wasMain = (bool) $source->is_main;
            $copy = $this->copy($sourceProductId, $productFileId, $targetProductId);
            $this->attachments->detach(
                AttachmentTargetType::Product,
                (int) $source->product_id,
                (int) $source->attachment_id,
            );
            if ($wasMain) {
                $source->update(['is_main' => false]);
                $next = ProductFile::query()
                    ->where('company_id', $this->companyId())
                    ->where('product_id', $source->product_id)
                    ->where('kind', ProductFileKind::Media->value)
                    ->where('id', '<>', $source->getKey())
                    ->whereHas('attachment', static fn ($query) => $query->whereNull('detached_at'))
                    ->orderBy('position')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();
                $next?->update(['is_main' => true]);
            }

            $copyId = $copy->getKey();
            if (! is_int($copyId)) {
                throw new LogicException('Moved product media persistence did not return an integer key.');
            }
            $this->auditMedia(
                $sourceId,
                'move_out',
                ['product_file_id' => $productFileId, 'was_main' => $wasMain],
                ['target_product_id' => $targetProductId, 'target_product_file_id' => $copyId],
            );

            return $copy;
        });
    }

    public function quarantine(int $productId, int $productFileId, string $reason): FileAsset
    {
        $id = $this->productId($productId);

        return DB::transaction(function () use ($id, $productFileId, $reason): FileAsset {
            $file = $this->mediaFile($id, $productFileId, true);
            $assetId = $this->assetId($file);
            $asset = $this->attachments->quarantine($assetId, $reason);
            $this->auditMedia(
                $id,
                'quarantine',
                ['product_file_id' => $productFileId, 'file_asset_id' => $assetId, 'quarantined' => false],
                [
                    'product_file_id' => $productFileId,
                    'file_asset_id' => $assetId,
                    'quarantined' => true,
                    'reason' => (string) $asset->quarantine_reason,
                ],
            );

            return $asset;
        });
    }

    public function releaseQuarantine(int $productId, int $productFileId): FileAsset
    {
        $id = $this->productId($productId);

        return DB::transaction(function () use ($id, $productFileId): FileAsset {
            $file = $this->mediaFile($id, $productFileId, true, true);
            $asset = $file->attachment?->fileAsset;
            if (! $asset instanceof FileAsset) {
                throw new LogicException('Product media quarantine release requires a file asset.');
            }
            $assetId = $this->assetId($file);
            $beforeReason = $asset->quarantine_reason;
            $wasQuarantined = $asset->quarantined_at !== null;
            $released = $this->attachments->releaseQuarantine($assetId);
            if ($wasQuarantined) {
                $this->auditMedia(
                    $id,
                    'release_quarantine',
                    [
                        'product_file_id' => $productFileId,
                        'file_asset_id' => $assetId,
                        'quarantined' => true,
                        'reason' => $beforeReason,
                    ],
                    ['product_file_id' => $productFileId, 'file_asset_id' => $assetId, 'quarantined' => false],
                );
            }

            return $released;
        });
    }

    /** @return Collection<int, ProductFile> */
    private function media(int $productId): Collection
    {
        return ProductFile::query()
            ->where('company_id', $this->companyId())
            ->where('product_id', $productId)
            ->where('kind', ProductFileKind::Media->value)
            ->whereHas('attachment', static fn ($query) => $query->whereNull('detached_at'))
            ->with('attachment.fileAsset')
            ->orderByDesc('is_main')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    private function mediaFile(
        int $productId,
        int $productFileId,
        bool $lock = false,
        bool $allowQuarantined = false,
    ): ProductFile {
        $query = ProductFile::query()
            ->where('company_id', $this->companyId())
            ->where('product_id', $productId)
            ->where('kind', ProductFileKind::Media->value)
            ->whereHas('attachment', static fn ($attachment) => $attachment->whereNull('detached_at'))
            ->with('attachment.fileAsset');
        if ($lock) {
            $query->lockForUpdate();
        }
        $file = $query->findOrFail($productFileId);
        $asset = $file->attachment?->fileAsset;
        if (! $asset instanceof FileAsset || $asset->archived_at !== null) {
            throw ValidationException::withMessages(['file' => 'Aktif medya dosyası bulunamadı.']);
        }
        if (! $allowQuarantined && $asset->quarantined_at !== null) {
            throw ValidationException::withMessages(['file' => 'Karantinadaki medya üzerinde işlem yapılamaz.']);
        }

        return $file;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function normalizeTransform(array $metadata): array
    {
        $unknown = array_diff(array_keys($metadata), ['crop', 'rotate', 'flip', 'resize']);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['transform' => 'Bilinmeyen görsel dönüşüm alanı: '.implode(', ', $unknown)]);
        }
        $normalized = [];

        if (array_key_exists('crop', $metadata)) {
            $crop = $metadata['crop'];
            if (! is_array($crop)) {
                throw ValidationException::withMessages(['transform.crop' => 'Crop alanı nesne olmalıdır.']);
            }
            $normalized['crop'] = [
                'x' => $this->integerRange($crop['x'] ?? null, 0, 100000, 'transform.crop.x'),
                'y' => $this->integerRange($crop['y'] ?? null, 0, 100000, 'transform.crop.y'),
                'width' => $this->integerRange($crop['width'] ?? null, 1, 100000, 'transform.crop.width'),
                'height' => $this->integerRange($crop['height'] ?? null, 1, 100000, 'transform.crop.height'),
            ];
        }

        if (array_key_exists('rotate', $metadata)) {
            $rotate = $this->integerRange($metadata['rotate'], 0, 270, 'transform.rotate');
            if (! in_array($rotate, [0, 90, 180, 270], true)) {
                throw ValidationException::withMessages(['transform.rotate' => 'Rotate yalnız 0, 90, 180 veya 270 olabilir.']);
            }
            $normalized['rotate'] = $rotate;
        }

        if (array_key_exists('flip', $metadata)) {
            $flip = $metadata['flip'];
            if (! is_array($flip)) {
                throw ValidationException::withMessages(['transform.flip' => 'Flip alanı nesne olmalıdır.']);
            }
            $horizontal = $flip['horizontal'] ?? false;
            $vertical = $flip['vertical'] ?? false;
            if (! is_bool($horizontal) || ! is_bool($vertical)) {
                throw ValidationException::withMessages(['transform.flip' => 'Flip değerleri boolean olmalıdır.']);
            }
            $normalized['flip'] = ['horizontal' => $horizontal, 'vertical' => $vertical];
        }

        if (array_key_exists('resize', $metadata)) {
            $resize = $metadata['resize'];
            if (! is_array($resize)) {
                throw ValidationException::withMessages(['transform.resize' => 'Resize alanı nesne olmalıdır.']);
            }
            $mode = $resize['mode'] ?? 'contain';
            if (! is_string($mode) || ! in_array($mode, ['contain', 'cover', 'stretch'], true)) {
                throw ValidationException::withMessages(['transform.resize.mode' => 'Resize modu contain, cover veya stretch olmalıdır.']);
            }
            $normalized['resize'] = [
                'width' => $this->integerRange($resize['width'] ?? null, 1, 10000, 'transform.resize.width'),
                'height' => $this->integerRange($resize['height'] ?? null, 1, 10000, 'transform.resize.height'),
                'mode' => $mode,
            ];
        }

        return $normalized;
    }

    private function integerRange(mixed $value, int $min, int $max, string $field): int
    {
        if (! is_int($value) || $value < $min || $value > $max) {
            throw ValidationException::withMessages([$field => $field.' değeri '.$min.'-'.$max.' aralığında tam sayı olmalıdır.']);
        }

        return $value;
    }

    private function assetId(ProductFile $file): int
    {
        $asset = $file->attachment?->fileAsset;
        $id = $asset?->getKey();

        return is_int($id) ? $id : throw new LogicException('Product media file asset must be persisted.');
    }

    private function nextPosition(int $productId): int
    {
        $last = ProductFile::query()
            ->where('company_id', $this->companyId())
            ->where('product_id', $productId)
            ->where('kind', ProductFileKind::Media->value)
            ->max('position');
        $position = is_numeric($last) ? ((int) $last) + 1 : 0;
        if ($position > 32767) {
            throw ValidationException::withMessages(['file' => 'Bu ürün için medya sıralama sınırına ulaşıldı.']);
        }

        return $position;
    }

    private function hasActiveMain(int $productId): bool
    {
        return ProductFile::query()
            ->where('company_id', $this->companyId())
            ->where('product_id', $productId)
            ->where('kind', ProductFileKind::Media->value)
            ->where('is_main', true)
            ->whereHas('attachment', static fn ($query) => $query->whereNull('detached_at'))
            ->exists();
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function auditMedia(int $productId, string $operation, ?array $before, ?array $after): void
    {
        $this->audit->record(
            AuditAction::ProductMediaUpdated,
            AuditTargetType::Product,
            $productId,
            before: $before,
            after: $after,
            metadata: ['operation' => $operation],
        );
    }

    private function productId(int $productId): int
    {
        $product = Product::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($productId);
        $id = $product->getKey();

        return is_int($id) ? $id : throw new LogicException('Product image operation requires a persisted product.');
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();

        return is_int($id) ? $id : throw new LogicException('Product image operation requires a persisted company.');
    }
}
