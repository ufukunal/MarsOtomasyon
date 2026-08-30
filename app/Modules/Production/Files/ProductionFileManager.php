<?php

namespace App\Modules\Production\Files;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Files\PrivateAttachmentManager;
use App\Modules\Core\Models\Attachment;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ProductionFileManager
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private PrivateAttachmentManager $attachments,
    ) {}

    /** @return Collection<int, Attachment> */
    public function all(int $orderId): Collection
    {
        $orderId = $this->orderId($orderId);

        return Attachment::query()
            ->where('company_id', $this->companyId())
            ->where('attachable_type', AttachmentTargetType::ProductionOrder->value)
            ->where('attachable_id', $orderId)
            ->with(['fileAsset', 'attachedBy', 'detachedBy'])
            ->orderByDesc('attached_at')
            ->get();
    }

    public function upload(int $orderId, UploadedFile $upload, ?string $label = null): Attachment
    {
        return $this->attachments->upload(
            AttachmentTargetType::ProductionOrder,
            $this->orderId($orderId),
            $upload,
            $label,
        );
    }

    public function download(int $orderId, int $attachmentId): StreamedResponse
    {
        return $this->attachments->download(
            AttachmentTargetType::ProductionOrder,
            $this->orderId($orderId),
            $attachmentId,
        );
    }

    public function detach(int $orderId, int $attachmentId): Attachment
    {
        return $this->attachments->detach(
            AttachmentTargetType::ProductionOrder,
            $this->orderId($orderId),
            $attachmentId,
        );
    }

    private function orderId(int $orderId): int
    {
        $order = ProductionOrder::query()->where('company_id', $this->companyId())->findOrFail($orderId);
        $key = $order->getKey();

        return is_int($key) ? $key : throw new LogicException('Production file target must be persisted.');
    }

    private function companyId(): int
    {
        $key = $this->companyContext->requireCompany()->getKey();

        return is_int($key) ? $key : throw new LogicException('Production file operation requires a persisted company.');
    }
}
