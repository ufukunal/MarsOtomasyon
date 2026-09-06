<?php

namespace App\Modules\Accounts\Crm;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Files\PrivateAttachmentManager;
use App\Modules\Core\Models\Attachment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class CrmFileManager
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private PrivateAttachmentManager $attachments,
    ) {}

    /** @return Collection<int, Attachment> */
    public function all(AttachmentTargetType $targetType, int $targetId): Collection
    {
        $id = $this->targetId($targetType, $targetId);

        return Attachment::query()
            ->where('company_id', $this->companyId())
            ->where('attachable_type', $targetType->value)
            ->where('attachable_id', $id)
            ->with(['fileAsset', 'attachedBy', 'detachedBy'])
            ->orderByDesc('attached_at')
            ->get();
    }

    public function upload(AttachmentTargetType $targetType, int $targetId, UploadedFile $upload, ?string $label = null): Attachment
    {
        return $this->attachments->upload($targetType, $this->targetId($targetType, $targetId), $upload, $label);
    }

    public function download(AttachmentTargetType $targetType, int $targetId, int $attachmentId): StreamedResponse
    {
        return $this->attachments->download($targetType, $this->targetId($targetType, $targetId), $attachmentId);
    }

    public function detach(AttachmentTargetType $targetType, int $targetId, int $attachmentId): Attachment
    {
        return $this->attachments->detach($targetType, $this->targetId($targetType, $targetId), $attachmentId);
    }

    private function targetId(AttachmentTargetType $targetType, int $targetId): int
    {
        $table = match ($targetType) {
            AttachmentTargetType::CrmLead => 'crm_leads',
            AttachmentTargetType::CrmOpportunity => 'crm_opportunities',
            default => throw new LogicException('Unsupported CRM attachment target.'),
        };

        if (! DB::table($table)->where('company_id', $this->companyId())->where('id', $targetId)->exists()) {
            throw new LogicException('CRM file target was not found in the active company.');
        }

        return $targetId;
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();

        return is_int($id) ? $id : throw new LogicException('CRM file operation requires a persisted company.');
    }
}
