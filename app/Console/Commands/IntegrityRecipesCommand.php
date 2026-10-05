<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsIntegrityCheck;
use App\Support\Integrity\Checks\RecipeIntegrityCheck;
use Illuminate\Console\Command;

class IntegrityRecipesCommand extends Command
{
    use RunsIntegrityCheck;

    protected $signature = 'integrity:recipes';

    protected $description = 'Üretim reçete revizyon ve component snapshot bütünlüğünü kontrol eder';

    public function handle(RecipeIntegrityCheck $check): int
    {
        return $this->runCheck($check);
    }
}
