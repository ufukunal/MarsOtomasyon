<?php

namespace App\Enums;

enum PrintType: string
{
    case A4 = 'a4';
    case ProductLabel = 'product_label';
    case CartonLabel = 'carton_label';
    case Receipt = 'receipt';
    case Report = 'report';
}
