<?php

namespace App\Modules\Core\Files;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Models\Attachment;
use Illuminate\Http\UploadedFile;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class CompanyFileManager
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private PrivateAttachmentManager $attachments,
    ) {}

    public function upload(UploadedFile $upload, ?string $label = null): Attachment
    {
        return $this->attachments->upload(AttachmentTargetType::Company, $this->companyId(), $upload, $label);
    }

    public function attachment(int $attachmentId): Attachment
    {
        return $this->attachments->attachment(AttachmentTargetType::Company, $this->companyId(), $attachmentId);
    }

    public function download(int $attachmentId): StreamedResponse
    {
        return $this->attachments->download(AttachmentTargetType::Company, $this->companyId(), $attachmentId);
    }

    public function detach(int $attachmentId): Attachment
    {
        return $this->attachments->detach(AttachmentTargetType::Company, $this->companyId(), $attachmentId);
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();

        return is_int($id) ? $id : throw new LogicException('Company file operation requires a persisted company.');
    }
}
