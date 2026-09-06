<?php

namespace App\Modules\Core\Preview;

use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\CadDerivativeJob;
use App\Modules\Core\Models\FileAsset;

interface CadDerivativeProvider
{
    public function provider(): string;

    public function version(): string;

    public function isCloud(): bool;

    /** @return list<string> Lowercase extensions without a leading dot. */
    public function supportedExtensions(): array;

    public function start(Attachment $attachment, FileAsset $asset): CadDerivativeResult;

    public function refresh(CadDerivativeJob $job): CadDerivativeResult;

    /** @return array{access_token:string,expires_in:int}|null */
    public function viewerToken(CadDerivativeJob $job): ?array;
}
