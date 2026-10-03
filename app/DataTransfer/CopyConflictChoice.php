<?php

namespace App\DataTransfer;

enum CopyConflictChoice: string
{
    case UseExisting = 'existing';
    case NewCode = 'new_code';
    case Cancel = 'cancel';
}
