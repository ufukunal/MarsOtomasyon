<?php

use Tests\Support\FixedSalesInvoiceClock;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Integration', 'Browser');

pest()->use(FixedSalesInvoiceClock::class)->in('Integration/SalesInvoices');
