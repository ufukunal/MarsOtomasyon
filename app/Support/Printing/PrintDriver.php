<?php

namespace App\Support\Printing;

use App\Enums\PrintType;
use App\Models\PrintProfile;

interface PrintDriver
{
    /** @param array<string, mixed> $payload */
    public function send(
        PrintType $type,
        array $payload,
        ?PrintProfile $profile,
    ): PrintResult;
}
