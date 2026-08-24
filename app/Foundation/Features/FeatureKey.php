<?php

namespace App\Foundation\Features;

enum FeatureKey: string
{
    case Foundation = 'foundation';
    case Customers = 'customers';
    case ProductStock = 'product_stock';
    case Sales = 'sales';
    case Purchasing = 'purchasing';
    case Production = 'production';
    case Treasury = 'treasury';
    case Instruments = 'instruments';
    case Returns = 'returns';
    case Import = 'import';
    case Commerce = 'commerce';
    case Communications = 'communications';
    case Reports = 'reports';
}
