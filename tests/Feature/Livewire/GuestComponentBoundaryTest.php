<?php

use App\Livewire\Channels\ChannelSyncCenter;
use App\Livewire\Imports\ImportCenter;
use App\Livewire\Pages\Stock\StockStatus;
use App\Livewire\Pages\Stock\TransferDetail;
use App\Livewire\Production\ProductionOrderCenter;
use App\Livewire\Production\RecipeCenter;
use App\Livewire\Reporting\ReportCenter;
use App\Livewire\Returns\ReturnCenter;
use Livewire\Livewire;

it('denies guests when Livewire mounts business modules directly', function (string $screen): void {
    auth()->logout();

    Livewire::test($screen)->assertForbidden();
})->with([
    ChannelSyncCenter::class,
    ImportCenter::class,
    StockStatus::class,
    TransferDetail::class,
    ProductionOrderCenter::class,
    RecipeCenter::class,
    ReportCenter::class,
    ReturnCenter::class,
]);
