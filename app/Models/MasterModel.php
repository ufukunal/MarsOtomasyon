<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class MasterModel extends Model
{
    protected $connection = 'master';
}
