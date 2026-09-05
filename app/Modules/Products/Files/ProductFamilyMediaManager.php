<?php

namespace App\Modules\Products\Files;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Files\PrivateAttachmentManager;
use App\Modules\Core\Models\Attachment;
use App\Modules\Products\Enums\ProductFileKind;
use App\Modules\Products\Models\ProductFamily;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class ProductFamilyMediaManager
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private PrivateAttachmentManager $attachments,
    ) {}

    public function upload(int $familyId, UploadedFile $upload, ?string $label = null): Attachment
    {
        $family = $this->family($familyId);
        $mime = mb_strtolower((string) $upload->getMimeType());
        if (! str_starts_with($mime, 'image/')) {
            throw new LogicException('Product family media must be an image.');
        }

        return $this->attachments->upload(AttachmentTargetType::ProductFamily, (int) $family->getKey(), $upload, $label);
    }

    public function linkExistingAsset(int $familyId, int $fileAssetId, ?string $label = null): Attachment
    {
        $family = $this->family($familyId);

        return $this->attachments->linkExistingAsset(
            AttachmentTargetType::ProductFamily,
            (int) $family->getKey(),
            $fileAssetId,
            $label,
        );
    }

    /** @return Collection<int,Attachment> */
    public function all(int $familyId): Collection
    {
        $family = $this->family($familyId);

        return Attachment::query()
            ->where('company_id', $this->companyId())
            ->where('attachable_type', AttachmentTargetType::ProductFamily->value)
            ->where('attachable_id', $family->getKey())
            ->whereNull('detached_at')
            ->whereHas('fileAsset', static fn ($query) => $query->whereNull('archived_at')->whereNull('quarantined_at'))
            ->with('fileAsset')
            ->orderBy('attached_at')
            ->orderBy('id')
            ->get();
    }

    public function setHero(int $familyId, int $attachmentId): ProductFamily
    {
        $family = $this->family($familyId);
        $attachment = $this->activeFamilyAttachment((int) $family->getKey(), $attachmentId);
        $content = is_array($family->shared_content) ? $family->shared_content : [];
        $content['hero_attachment_id'] = (int) $attachment->getKey();
        $family->update(['shared_content' => $content]);

        return $family->refresh();
    }

    public function detach(int $familyId, int $attachmentId): Attachment
    {
        $family = $this->family($familyId);
        $attachment = $this->attachments->detach(
            AttachmentTargetType::ProductFamily,
            (int) $family->getKey(),
            $attachmentId,
        );
        $content = is_array($family->shared_content) ? $family->shared_content : [];
        if ((int) ($content['hero_attachment_id'] ?? 0) === $attachmentId) {
            unset($content['hero_attachment_id']);
            $family->update(['shared_content' => $content === [] ? null : $content]);
        }

        return $attachment;
    }

    /**
     * Resolve explicit family hero first, then deterministic first active product media.
     * Null is the stable placeholder signal for the presentation layer.
     */
    public function hero(int $familyId): ?Attachment
    {
        $family = $this->family($familyId);
        $content = is_array($family->shared_content) ? $family->shared_content : [];
        $explicitId = (int) ($content['hero_attachment_id'] ?? 0);
        if ($explicitId > 0) {
            $explicit = $this->activeFamilyAttachment((int) $family->getKey(), $explicitId, false);
            if ($explicit instanceof Attachment) {
                return $explicit->load('fileAsset');
            }
        }

        $attachmentId = DB::table('product_variant_relations as pvr')
            ->join('product_files as pf', function ($join): void {
                $join->on('pf.company_id', '=', 'pvr.company_id')->on('pf.product_id', '=', 'pvr.product_id');
            })
            ->join('attachments as a', function ($join): void {
                $join->on('a.company_id', '=', 'pf.company_id')->on('a.id', '=', 'pf.attachment_id');
            })
            ->join('file_assets as fa', function ($join): void {
                $join->on('fa.company_id', '=', 'a.company_id')->on('fa.id', '=', 'a.file_asset_id');
            })
            ->where('pvr.company_id', $this->companyId())
            ->where('pvr.product_family_id', $family->getKey())
            ->where('pf.kind', ProductFileKind::Media->value)
            ->whereNull('a.detached_at')
            ->whereNull('fa.archived_at')
            ->whereNull('fa.quarantined_at')
            ->orderBy('pvr.product_id')
            ->orderByDesc('pf.is_main')
            ->orderBy('pf.position')
            ->orderBy('pf.id')
            ->value('a.id');

        if (! is_numeric($attachmentId)) {
            return null;
        }

        return Attachment::query()->with('fileAsset')->find((int) $attachmentId);
    }

    private function activeFamilyAttachment(int $familyId, int $attachmentId, bool $required = true): ?Attachment
    {
        $attachment = Attachment::query()
            ->where('company_id', $this->companyId())
            ->where('attachable_type', AttachmentTargetType::ProductFamily->value)
            ->where('attachable_id', $familyId)
            ->whereNull('detached_at')
            ->whereHas('fileAsset', static fn ($query) => $query->whereNull('archived_at')->whereNull('quarantined_at'))
            ->find($attachmentId);
        if ($required && ! $attachment instanceof Attachment) {
            throw new LogicException('Active product family media attachment was not found.');
        }

        return $attachment;
    }

    private function family(int $familyId): ProductFamily
    {
        $family = ProductFamily::query()->where('company_id', $this->companyId())->find($familyId);
        if (! $family instanceof ProductFamily) {
            throw new LogicException('Product family media target was not found for active company.');
        }

        return $family;
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();

        return is_int($id) ? $id : throw new LogicException('Product family media operation requires a persisted company.');
    }
}
