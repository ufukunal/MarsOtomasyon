<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'Demo seed verileri production ortamında çalıştırılamaz. Kurulum için Setup Wizard kullanın.',
            );
        }

        $this->call([
            CompanySeeder::class,
            RoleSeeder::class,
        ]);
    }
}
