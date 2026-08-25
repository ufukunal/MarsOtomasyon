<?php

namespace App\Modules\Accounts\Files;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Files\PrivateAttachmentManager;
use App\Modules\Core\Models\Attachment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class AccountFileManager
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private PrivateAttachmentManager $attachments,
    ) {}

    /** @return Collection<int, Attachment> */
    public function all(int $accountId): Collection
    {
        $id = $this->accountId($accountId);

        return Attachment::query()
            ->where('company_id', $this->companyId())
            ->where('attachable_type', AttachmentTargetType::Account->value)
            ->where('attachable_id', $id)
            ->with(['fileAsset', 'attachedBy', 'detachedBy'])
            ->orderByDesc('attached_at')
            ->get();
    }

    public function upload(int $accountId, UploadedFile $upload, ?string $label = null): Attachment
    {
        return $this->attachments->upload(AttachmentTargetType::Account, $this->accountId($accountId), $upload, $label);
    }

    public function attachment(int $accountId, int $attachmentId): Attachment
    {
        return $this->attachments->attachment(AttachmentTargetType::Account, $this->accountId($accountId), $attachmentId);
    }

    public function download(int $accountId, int $attachmentId): StreamedResponse
    {
        return $this->attachments->download(AttachmentTargetType::Account, $this->accountId($accountId), $attachmentId);
    }

    public function detach(int $accountId, int $attachmentId): Attachment
    {
        return $this->attachments->detach(AttachmentTargetType::Account, $this->accountId($accountId), $attachmentId);
    }

    private function accountId(int $accountId): int
    {
        $account = Account::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($accountId);
        $id = $account->getKey();

        return is_int($id) ? $id : throw new LogicException('Account file target must be persisted.');
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();

        return is_int($id) ? $id : throw new LogicException('Account file operation requires a persisted company.');
    }
}
