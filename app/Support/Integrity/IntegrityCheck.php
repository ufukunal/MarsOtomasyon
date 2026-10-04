<?php

namespace App\Support\Integrity;

interface IntegrityCheck
{
    public function name(): string;

    public function run(): IntegrityResult;
}
