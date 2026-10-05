<?php

use App\Support\Reporting\Queries\BankMovementReportQuery;
use App\Support\Reporting\Queries\CashMovementReportQuery;
use App\Support\Reporting\Queries\ChannelOrderReportQuery;
use App\Support\Reporting\Queries\ChannelSyncStatusReportQuery;
use App\Support\Reporting\Queries\ContactTransactionReportQuery;
use App\Support\Reporting\Queries\ImportFileReportQuery;
use App\Support\Reporting\Queries\ProductionOrderReportQuery;
use App\Support\Reporting\Queries\QuarantineReportQuery;
use App\Support\Reporting\Queries\ReturnDocumentReportQuery;
use App\Support\Reporting\Queries\SalesInvoiceReportQuery;
use App\Support\Reporting\Queries\SecurityPortfolioReportQuery;
use App\Support\Reporting\Queries\StockBalanceReportQuery;
use App\Support\Reporting\Queries\SupplierInvoiceReportQuery;

return [
    'max_page_size' => 250,

    'queries' => [
        SalesInvoiceReportQuery::class,
        SupplierInvoiceReportQuery::class,
        ContactTransactionReportQuery::class,
        StockBalanceReportQuery::class,
        CashMovementReportQuery::class,
        BankMovementReportQuery::class,
        SecurityPortfolioReportQuery::class,
        ReturnDocumentReportQuery::class,
        QuarantineReportQuery::class,
        ImportFileReportQuery::class,
        ProductionOrderReportQuery::class,
        ChannelOrderReportQuery::class,
        ChannelSyncStatusReportQuery::class,
    ],
];
