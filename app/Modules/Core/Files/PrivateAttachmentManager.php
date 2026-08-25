<?php

namespace App\Modules\Core\Files;

use App\Foundation\Clock\Clock;
use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\FileAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final readonly class PrivateAttachmentManager
{
    private const MAX_UPLOAD_BYTES = 52_428_800;

    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'html', 'htm', 'svg', 'js', 'mjs',
        'exe', 'dll', 'com', 'bat', 'cmd', 'sh', 'ps1', 'jar', 'msi', 'scr', 'vbs', 'wsf',
    ];

    private const BLOCKED_MIME_TYPES = [
        'text/html', 'image/svg+xml', 'application/x-httpd-php', 'application/x-php',
        'application/x-executable', 'application/x-msdownload',
    ];

    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
        private Clock $clock,
    ) {}

    public function upload(AttachmentTargetType $type, int $targetId, UploadedFile $upload, ?string $label = null): Attachment
    {
        $companyId = $this->companyId();
        $this->assertCoreTarget($type, $targetId, $companyId);
        $actorId = $this->actorId();
        $metadata = $this->inspect($upload);
        $storageName = (string) Str::ulid();
        $storedKey = Storage::disk('local')->putFileAs('companies/'.$companyId.'/files', $upload, $storageName);

        if (! is_string($storedKey) || $storedKey === '') {
            throw ValidationException::withMessages(['file' => 'Dosya private storage alanına kaydedilemedi.']);
        }

        try {
            return DB::transaction(function () use ($companyId, $actorId, $type, $targetId, $metadata, $storedKey, $label): Attachment {
                $asset = FileAsset::query()->create([
                    'company_id' => $companyId,
                    'uploaded_by_user_id' => $actorId,
                    'storage_disk' => 'local',
                    'storage_key' => $storedKey,
                    'original_name' => $metadata['original_name'],
                    'mime_type' => $metadata['mime_type'],
                    'client_extension' => $metadata['client_extension'],
                    'size_bytes' => $metadata['size_bytes'],
                    'sha256' => $metadata['sha256'],
                ]);

                $assetId = $asset->getKey();
                if (! is_int($assetId)) {
                    throw new LogicException('File asset persistence did not return an integer key.');
                }

                $attachment = Attachment::query()->create([
                    'company_id' => $companyId,
                    'file_asset_id' => $assetId,
                    'attachable_type' => $type,
                    'attachable_id' => $targetId,
                    'label' => $label === null || trim($label) === '' ? null : trim($label),
                    'attached_by_user_id' => $actorId,
                    'attached_at' => $this->clock->now(),
                ]);

                $attachmentId = $attachment->getKey();
                if (! is_int($attachmentId)) {
                    throw new LogicException('Attachment persistence did not return an integer key.');
                }

                $this->audit->record(
                    AuditAction::FileUploaded,
                    AuditTargetType::Attachment,
                    $attachmentId,
                    after: [
                        'file_asset_id' => $assetId,
                        'attachment_id' => $attachmentId,
                        'mime_type' => $metadata['mime_type'],
                        'size_bytes' => $metadata['size_bytes'],
                        'sha256' => $metadata['sha256'],
                        'target_type' => $type->value,
                        'target_id' => $targetId,
                    ],
                );

                return $attachment->setRelation('fileAsset', $asset);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedKey);

            throw $exception;
        }
    }

    public function attachment(AttachmentTargetType $type, int $targetId, int $attachmentId): Attachment
    {
        $companyId = $this->companyId();
        $this->assertCoreTarget($type, $targetId, $companyId);

        return Attachment::query()
            ->where('company_id', $companyId)
            ->where('attachable_type', $type->value)
            ->where('attachable_id', $targetId)
            ->with(['fileAsset', 'attachedBy', 'detachedBy'])
            ->findOrFail($attachmentId);
    }

    public function download(AttachmentTargetType $type, int $targetId, int $attachmentId): StreamedResponse
    {
        $attachment = $this->attachment($type, $targetId, $attachmentId);
        abort_if($attachment->isDetached(), 404);

        $asset = $attachment->fileAsset;
        abort_if(! $asset instanceof FileAsset || $asset->archived_at !== null, 404);
        abort_unless(Storage::disk((string) $asset->storage_disk)->exists((string) $asset->storage_key), 410, 'Dosya storage alanında bulunamadı.');

        return Storage::disk((string) $asset->storage_disk)->download(
            (string) $asset->storage_key,
            (string) $asset->original_name,
            [
                'Content-Type' => (string) $asset->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ],
        );
    }

    public function detach(AttachmentTargetType $type, int $targetId, int $attachmentId): Attachment
    {
        $companyId = $this->companyId();
        $this->assertCoreTarget($type, $targetId, $companyId);
        $actorId = $this->actorId();

        return DB::transaction(function () use ($type, $targetId, $attachmentId, $companyId, $actorId): Attachment {
            $attachment = Attachment::query()
                ->where('company_id', $companyId)
                ->where('attachable_type', $type->value)
                ->where('attachable_id', $targetId)
                ->lockForUpdate()
                ->findOrFail($attachmentId);

            if ($attachment->isDetached()) {
                return $attachment;
            }

            $now = $this->clock->now();
            $attachment->update(['detached_at' => $now, 'detached_by_user_id' => $actorId]);

            $activeExists = Attachment::query()->where('file_asset_id', $attachment->file_asset_id)->whereNull('detached_at')->exists();
            if (! $activeExists) {
                FileAsset::query()
                    ->where('company_id', $companyId)
                    ->whereKey($attachment->file_asset_id)
                    ->whereNull('archived_at')
                    ->update(['archived_at' => $now, 'archived_by_user_id' => $actorId, 'updated_at' => $now]);
            }

            $key = $attachment->getKey();
            if (! is_int($key)) {
                throw new LogicException('Attachment persistence did not return an integer key.');
            }

            $this->audit->record(
                AuditAction::AttachmentDetached,
                AuditTargetType::Attachment,
                $key,
                before: ['detached' => false],
                after: ['detached' => true, 'file_asset_id' => (int) $attachment->file_asset_id],
            );

            return $attachment;
        });
    }

    /** @return array{original_name:string,mime_type:string,client_extension:string|null,size_bytes:int,sha256:string} */
    private function inspect(UploadedFile $upload): array
    {
        $size = $upload->getSize();
        if (! is_int($size) || $size < 1 || $size > self::MAX_UPLOAD_BYTES) {
            throw ValidationException::withMessages(['file' => 'Dosya boyutu 1 byte ile 50 MB arasında olmalıdır.']);
        }

        $originalName = basename(str_replace('\\', '/', $upload->getClientOriginalName()));
        $originalName = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName));
        if ($originalName === '') {
            throw ValidationException::withMessages(['file' => 'Dosya adı geçersiz.']);
        }
        $originalName = mb_substr($originalName, 0, 255);

        $extension = mb_strtolower(trim($upload->getClientOriginalExtension()));
        $extension = $extension === '' ? null : mb_substr($extension, 0, 24);
        if ($extension !== null && in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['file' => 'Bu dosya uzantısına güvenlik nedeniyle izin verilmiyor.']);
        }

        $mime = mb_strtolower((string) $upload->getMimeType());
        if ($mime === '' || in_array($mime, self::BLOCKED_MIME_TYPES, true)) {
            throw ValidationException::withMessages(['file' => 'Dosya türüne güvenlik nedeniyle izin verilmiyor.']);
        }

        $realPath = $upload->getRealPath();
        if (! is_string($realPath) || $realPath === '') {
            throw ValidationException::withMessages(['file' => 'Dosya içeriği doğrulanamadı.']);
        }

        $sha256 = hash_file('sha256', $realPath);
        if (! is_string($sha256) || strlen($sha256) !== 64) {
            throw ValidationException::withMessages(['file' => 'Dosya checksum değeri üretilemedi.']);
        }

        return [
            'original_name' => $originalName,
            'mime_type' => $mime,
            'client_extension' => $extension,
            'size_bytes' => $size,
            'sha256' => $sha256,
        ];
    }

    private function assertCoreTarget(AttachmentTargetType $type, int $targetId, int $companyId): void
    {
        if ($targetId < 1) {
            throw new LogicException('Attachment target must be persisted.');
        }
        if ($type === AttachmentTargetType::Company && $targetId !== $companyId) {
            throw new LogicException('Company attachment target must match the active company.');
        }
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();

        return is_int($id) ? $id : throw new LogicException('File operation requires a persisted company.');
    }

    private function actorId(): int
    {
        $id = Auth::id();

        return is_int($id) ? $id : throw new LogicException('File operation requires an authenticated actor.');
    }
}
