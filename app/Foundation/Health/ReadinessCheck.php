<?php

namespace App\Foundation\Health;

interface ReadinessCheck
{
    public function check(): ReadinessResult;
}
