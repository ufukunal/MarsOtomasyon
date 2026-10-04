<?php

namespace App\Models;

use App\Support\Period\PeriodContext;
use Illuminate\Database\Eloquent\Model;

abstract class PeriodModel extends Model
{
    protected $connection = 'period';

    public function getConnectionName()
    {
        PeriodContext::ensure();

        return parent::getConnectionName();
    }
}
