<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('mars:status', function (): void {
    $this->info('MarsOtomasyon foundation is bootable.');
})->purpose('Show the MarsOtomasyon foundation status');
