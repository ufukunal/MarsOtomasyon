<?php

namespace App\Modules\Products\Files;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Files\PrivateAttachmentManager;
use App\Modules\Products\Enums\ProductFileKind;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductFile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final readonly class ProductFileManager
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private PrivateAttachmentManager $attachments,
    ) {}

    /** @return Collection<int, ProductFile> */
    public function all(int $productId): Collection
    {
        $id = $this->productId($productId);

        return ProductFile::query()
            ->where('company_id', $this->companyId())
            ->where('product_id', $id)
            ->whereHas('attachment', static fn ($query) => $query->whereNull('detached_at'))
            ->with(['attachment.fileAsset', 'attachment.attachedBy'])
            ->orderBy('kind')
            ->orderByDesc('is_main')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function upload(
        int $productId,
        ProductFileKind $kind,
        UploadedFile $upload,
        ?string $label = null,
    ): ProductFile {
        $id = $this->productId($productId);
        $this->assertKindAcceptsUpload($kind, $upload);
        $attachment = $this->attachments->upload(AttachmentTargetType::Product, $id, $upload, $label);
        $attachmentId = $attachment->getKey();
        if (! is_int($attachmentId)) {
            throw new LogicException('Product attachment persistence did not return an integer key.');
        }

        try {
            $productFile = DB::transaction(function () use ($id, $kind, $attachmentId): ProductFile {
                $position = $this->nextPosition($id, $kind);
                $isMain = $kind === ProductFileKind::Media && ! $this->hasActiveMainMedia($id);

                return ProductFile::query()->create([
                    'company_id' => $this->companyId(),
                    'product_id' => $id,
                    'attachment_id' => $attachmentId,
                    'kind' => $kind,
                    'position' => $position,
                    'is_main' => $isMain,
                ]);
            });
        } catch (Throwable $exception) {
            $this->attachments->detach(AttachmentTargetType::Product, $id, $attachmentId);

            throw $exception;
        }

        return $productFile->load(['attachment.fileAsset', 'attachment.attachedBy']);
    }

    public function download(int $productId, int $productFileId): StreamedResponse
    {
        $file = $this->file($productId, $productFileId);

        return $this->attachments->download(
            AttachmentTargetType::Product,
            (int) $file->product_id,
            (int) $file->attachment_id,
        );
    }

    public function detach(int $productId, int $productFileId): ProductFile
    {
        $file = $this->file($productId, $productFileId);

        return DB::transaction(function () use ($file): ProductFile {
            $this->attachments->detach(
                AttachmentTargetType::Product,
                (int) $file->product_id,
                (int) $file->attachment_id,
            );

            if ($file->getRawOriginal('kind') === ProductFileKind::Media->value && $file->is_main) {
                $file->update(['is_main' => false]);
                $next = ProductFile::query()
                    ->where('company_id', $this->companyId())
                    ->where('product_id', $file->product_id)
                    ->where('kind', ProductFileKind::Media->value)
                    ->where('id', '<>', $file->getKey())
                    ->whereHas('attachment', static fn ($query) => $query->whereNull('detached_at'))
                    ->orderBy('position')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();
                $next?->update(['is_main' => true]);
            }

            return $file->refresh();
        });
    }

    private function file(int $productId, int $productFileId): ProductFile
    {
        $id = $this->productId($productId);

        return ProductFile::query()
            ->where('company_id', $this->companyId())
            ->where('product_id', $id)
            ->with('attachment')
            ->findOrFail($productFileId);
    }

    private function assertKindAcceptsUpload(ProductFileKind $kind, UploadedFile $upload): void
    {
        if ($kind !== ProductFileKind::Media) {
            return;
        }

        $mime = mb_strtolower((string) $upload->getMimeType());
        if (! str_starts_with($mime, 'image/')) {
            throw ValidationException::withMessages([
                'file' => 'Medya alanına yalnız doğrulanmış görsel dosyaları yüklenebilir.',
            ]);
        }
    }

    private function hasActiveMainMedia(int $productId): bool
    {
        return ProductFile::query()
            ->where('company_id', $this->companyId())
            ->where('product_id', $productId)
            ->where('kind', ProductFileKind::Media->value)
            ->where('is_main', true)
            ->whereHas('attachment', static fn ($query) => $query->whereNull('detached_at'))
            ->exists();
    }

    private function nextPosition(int $productId, ProductFileKind $kind): int
    {
        $last = ProductFile::query()
            ->where('company_id', $this->companyId())
            ->where('product_id', $productId)
            ->where('kind', $kind->value)
            ->max('position');
        $position = is_numeric($last) ? ((int) $last) + 1 : 0;
        if ($position > 32767) {
            throw ValidationException::withMessages(['file' => 'Bu ürün için dosya sıralama sınırına ulaşıldı.']);
        }

        return $position;
    }

    private function productId(int $productId): int
    {
        $product = Product::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($productId);
        $id = $product->getKey();

        return is_int($id) ? $id : throw new LogicException('Product file target must be persisted.');
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();

        return is_int($id) ? $id : throw new LogicException('Product file operation requires a persisted company.');
    }
}
