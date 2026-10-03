<?php

namespace App\Support\Printing;

use App\Enums\PrintType;
use App\Models\PrintProfile;

interface PrintDriver
{
    public function send(
        PrintType $type,
        array $payload,
        ?PrintProfile $profile,
    ): PrintResult;
}
