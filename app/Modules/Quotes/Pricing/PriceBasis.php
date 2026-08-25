<?php

namespace App\Modules\Quotes\Pricing;

enum PriceBasis: string
{
    case Net = 'net';
    case Gross = 'gross';
}
