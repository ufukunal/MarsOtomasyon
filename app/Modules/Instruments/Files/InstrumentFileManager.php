<?php

namespace App\Modules\Instruments\Files;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Files\PrivateAttachmentManager;
use App\Modules\Core\Models\Attachment;
use App\Modules\Instruments\Models\Instrument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class InstrumentFileManager
{
    public function __construct(private ActiveCompanyContext $companyContext, private PrivateAttachmentManager $attachments) {}

    /** @return Collection<int, Attachment> */
    public function all(int $instrumentId): Collection
    {
        $id = $this->instrumentId($instrumentId);
        return Attachment::query()->where('company_id', $this->companyId())
            ->where('attachable_type', AttachmentTargetType::Instrument->value)->where('attachable_id', $id)
            ->with(['fileAsset', 'attachedBy', 'detachedBy'])->orderByDesc('attached_at')->get();
    }

    public function upload(int $instrumentId, UploadedFile $upload, string $side): Attachment
    {
        $side = strtolower(trim($side));
        if (! in_array($side, ['front', 'back'], true)) throw new InvalidArgumentException('Çek/senet görsel yönü front veya back olmalıdır.');
        $id = $this->instrumentId($instrumentId);
        $existing = Attachment::query()->where('company_id', $this->companyId())
            ->where('attachable_type', AttachmentTargetType::Instrument->value)->where('attachable_id', $id)
            ->where('label', $side)->whereNull('detached_at')->get();
        foreach ($existing as $attachment) {
            $key = $attachment->getKey();
            if (is_int($key)) $this->attachments->detach(AttachmentTargetType::Instrument, $id, $key);
        }
        return $this->attachments->upload(AttachmentTargetType::Instrument, $id, $upload, $side);
    }

    public function download(int $instrumentId, int $attachmentId): StreamedResponse
    {
        return $this->attachments->download(AttachmentTargetType::Instrument, $this->instrumentId($instrumentId), $attachmentId);
    }

    public function detach(int $instrumentId, int $attachmentId): Attachment
    {
        return $this->attachments->detach(AttachmentTargetType::Instrument, $this->instrumentId($instrumentId), $attachmentId);
    }

    private function instrumentId(int $instrumentId): int
    {
        $instrument = Instrument::query()->where('company_id', $this->companyId())->findOrFail($instrumentId);
        $id = $instrument->getKey();
        return is_int($id) ? $id : throw new LogicException('Instrument file target must be persisted.');
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();
        return is_int($id) ? $id : throw new LogicException('Instrument file operation requires a persisted company.');
    }
}
