<?php

namespace App\Modules\Core\Preview;

use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\FileAsset;

interface CadDerivativeProvider
{
    public function provider(): string;

    public function version(): string;

    public function isCloud(): bool;

    /** @return list<string> Lowercase extensions without a leading dot. */
    public function supportedExtensions(): array;

    /**
     * @return array{provider_job_id:?string,preview_kind:string,manifest:array<string,mixed>,derivative_sha256:?string,expires_at:?string}
     */
    public function translate(Attachment $attachment, FileAsset $asset): array;
}
