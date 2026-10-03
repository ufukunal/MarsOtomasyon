<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('mars:about', function (): void {
    $this->info('MarsOtomasyon');
})->purpose('MarsOtomasyon uygulama bilgisini gösterir');
