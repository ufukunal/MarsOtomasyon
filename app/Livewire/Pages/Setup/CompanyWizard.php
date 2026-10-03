<?php

namespace App\Livewire\Pages\Setup;

use App\Actions\Periods\CreatePeriod;
use App\Models\Company;
use App\Models\User;
use App\Support\Auth\CompanyRoleProvisioner;
use App\Support\Period\PeriodContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class CompanyWizard extends Component
{
    public int $step = 1;

    public string $code = '';
    public string $name = '';
    public string $legalName = '';
    public string $dbPrefix = '';
    public string $taxOffice = '';
    public string $taxNumber = '';
    public string $address = '';
    public string $baseCurrency = 'TRY';

    public int $year;
    public int $defaultTermDays = 30;
    public string $costDeviationThreshold = '25.0000';
    public string $defaultVatRate = '20';

    public string $warehouseCode = 'MERKEZ';
    public string $warehouseName = 'Merkez Depo';

    public string $adminName = '';
    public string $adminEmail = '';
    public string $adminPassword = '';

    public function mount(): void
    {
        $this->year = now()->year;

        if (User::query()->exists()) {
            abort_unless(auth()->check() && auth()->user()->can('companies.create'), 403);

            $this->adminName = auth()->user()->name;
            $this->adminEmail = auth()->user()->email;
        }
    }

    public function next(): void
    {
        $this->validateStep($this->step);
        $this->step = min(8, $this->step + 1);
    }

    public function previous(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function finish(CreatePeriod $createPeriod): void
    {
        for ($step = 1; $step <= 8; $step++) {
            $this->validateStep($step);
        }

        $company = Company::query()->create([
            'code' => $this->code,
            'name' => $this->name,
            'legal_name' => $this->legalName ?: null,
            'db_prefix' => $this->dbPrefix,
            'tax_office' => $this->taxOffice ?: null,
            'tax_number' => $this->taxNumber ?: null,
            'address' => $this->address ?: null,
            'default_term_days' => $this->defaultTermDays,
            'cost_deviation_threshold' => $this->costDeviationThreshold,
            'base_currency' => $this->baseCurrency,
        ]);

        $period = null;

        try {
            $period = $createPeriod->handle($company, $this->year);

            if (! Schema::connection('period')->hasTable('locations')) {
                throw new \RuntimeException(
                    'Kurulum sihirbazının lokasyon adımı Faz 1 locations şeması kurulmadan tamamlanamaz.',
                );
            }

            DB::connection('period')->table('locations')->insert([
                'code' => $this->warehouseCode,
                'name' => $this->warehouseName,
                'kind' => 'warehouse',
                'is_default' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (config('numbering.prefixes', []) as $documentType => $prefix) {
                DB::connection('period')->table('number_series')->updateOrInsert(
                    ['document_type' => $documentType, 'year' => $this->year],
                    ['prefix' => $prefix, 'last_number' => 0, 'padding' => 5, 'updated_at' => now(), 'created_at' => now()],
                );
            }

            $user = auth()->user();

            if (! $user) {
                $user = User::query()->create([
                    'name' => $this->adminName,
                    'email' => $this->adminEmail,
                    'password' => Hash::make($this->adminPassword),
                    'is_active' => true,
                ]);
            }

            DB::connection('master')->table('company_user')->insertOrIgnore([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::connection('master')->table('period_user_access')->insertOrIgnore([
                'period_id' => $period->id,
                'user_id' => $user->id,
                'is_active' => true,
                'permission_overrides' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $roles = app(CompanyRoleProvisioner::class)->handle($company);
            setPermissionsTeamId($company->id);
            $user->unsetRelation('roles');
            $user->assignRole($roles['Yönetici']);

            $user->forceFill([
                'last_company_id' => $company->id,
                'last_period_id' => $period->id,
            ])->save();

            PeriodContext::use($company->id, $period->id);
        } catch (\Throwable $exception) {
            PeriodContext::clear();

            if ($period?->exists) {
                $databaseName = $period->database_name;
                $period->delete();

                DB::connection('master')->statement(
                    sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE)', $databaseName),
                );
            }

            $company->delete();

            throw $exception;
        }

        $this->redirect('/', navigate: false);
    }

    private function validateStep(int $step): void
    {
        match ($step) {
            1 => $this->validate([
                'code' => ['required', 'string', 'max:20', 'unique:master.companies,code'],
                'name' => ['required', 'string', 'max:255'],
                'dbPrefix' => ['required', 'regex:/^[A-Za-z0-9_]+$/', 'max:30', 'unique:master.companies,db_prefix'],
                'baseCurrency' => ['required', 'size:3'],
            ]),
            2 => $this->validate(['year' => ['required', 'integer', 'between:2000,2200']]),
            3 => $this->validate([
                'defaultTermDays' => ['required', 'integer', 'min:0', 'max:3650'],
                'costDeviationThreshold' => ['required', 'decimal:0,4'],
                'defaultVatRate' => ['required', 'decimal:0,4'],
            ]),
            4 => $this->validate([
                'warehouseCode' => ['required', 'string', 'max:40'],
                'warehouseName' => ['required', 'string', 'max:255'],
            ]),
            6 => $this->validate([
                'adminName' => ['required', 'string', 'max:255'],
                'adminEmail' => ['required', 'email'],
                'adminPassword' => User::query()->exists()
                    ? ['nullable']
                    : ['required', 'string', 'min:10', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/'],
            ]),
            default => null,
        };
    }

    public function render(): View
    {
        return view('livewire.pages.setup.company-wizard')->layout('layouts.guest', [
            'title' => 'Firma Kurulumu',
        ]);
    }
}
