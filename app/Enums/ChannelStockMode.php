<?php

namespace App\Enums;

enum ChannelStockMode: string
{
    case Stock = 'stock';
    case Production = 'production';
    case Manual = 'manual';
}
