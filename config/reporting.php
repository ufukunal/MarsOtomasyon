<?php

use App\Support\Reporting\Queries\SalesInvoiceReportQuery;
use App\Support\Reporting\Queries\StockBalanceReportQuery;

return [
    'max_page_size' => 250,

    'queries' => [
        SalesInvoiceReportQuery::class,
        StockBalanceReportQuery::class,
    ],
];
