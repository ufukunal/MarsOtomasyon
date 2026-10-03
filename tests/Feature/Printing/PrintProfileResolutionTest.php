<?php

use App\Enums\PrintType;
use App\Models\PrintProfile;
use App\Support\Printing\PrintManager;

it('user+machine sonra user sonra şirket varsayılanı sırasıyla çözülür', function () {
    [$company, $period] = $this->createCompanyWithPeriod('PRINT');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $default = PrintProfile::query()->create([
        'company_id' => $company->id,
        'print_type' => PrintType::A4->value,
        'printer_name' => 'DEFAULT',
    ]);

    $userProfile = PrintProfile::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'print_type' => PrintType::A4->value,
        'printer_name' => 'USER',
    ]);

    $machine = PrintProfile::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'machine_key' => 'MACHINE-1',
        'print_type' => PrintType::A4->value,
        'printer_name' => 'MACHINE',
    ]);

    app('request')->cookies->set('machine_key', 'MACHINE-1');
    expect(PrintManager::resolveProfile(PrintType::A4)?->id)->toBe($machine->id);

    app('request')->cookies->remove('machine_key');
    expect(PrintManager::resolveProfile(PrintType::A4)?->id)->toBe($userProfile->id);

    auth()->logout();
    expect(PrintManager::resolveProfile(PrintType::A4)?->id)->toBe($default->id);
});
