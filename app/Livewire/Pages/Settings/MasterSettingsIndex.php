<?php

namespace App\Livewire\Pages\Settings;

use App\Support\Period\PeriodContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only UAT-friendly views of authorized Master administration data.
 * Never exposes secrets, grants roles, or mutates production services.
 */
final class MasterSettingsIndex extends Component
{
    use WithPagination;

    public string $section = '';

    private const SECTIONS = [
        'companies' => ['title' => 'Şirketler', 'permission' => 'companies.view'],
        'users' => ['title' => 'Kullanıcılar', 'permission' => 'users.view'],
        'roles' => ['title' => 'Roller', 'permission' => 'roles.view'],
        'audit' => ['title' => 'İşlem Geçmişi', 'permission' => 'audit.view'],
        'print-profiles' => ['title' => 'Yazdırma Profilleri', 'permission' => 'print_profiles.view'],
        'company-copy-permissions' => ['title' => 'Şirket Bağlantıları', 'permission' => 'company_copy_permissions.view'],
    ];

    public function mount(): void
    {
        $section = str_replace('settings.', '', (string) Route::currentRouteName());
        abort_unless(isset(self::SECTIONS[$section]), 404);

        $this->section = $section;
    }

    public function render(): View
    {
        $config = self::SECTIONS[$this->section] ?? null;
        abort_unless($config !== null, 404);
        Gate::authorize($config['permission']);

        $companyId = (int) PeriodContext::companyId();
        abort_unless($companyId > 0, 403);
        $master = DB::connection('master');

        [$query, $columns] = match ($this->section) {
            'companies' => [
                $master->table('companies')
                    ->whereIn('id', $master->table('company_user')
                        ->where('user_id', auth()->id())->select('company_id'))
                    ->select(['id', 'code', 'name', 'base_currency', 'is_active']),
                ['code' => 'Kod', 'name' => 'Şirket', 'base_currency' => 'Para Birimi', 'is_active' => 'Aktif'],
            ],
            'users' => [
                $master->table('users as u')
                    ->join('company_user as cu', 'cu.user_id', '=', 'u.id')
                    ->where('cu.company_id', $companyId)
                    ->select(['u.id', 'u.name', 'u.email', 'u.is_active']),
                ['name' => 'Ad Soyad', 'email' => 'E-posta', 'is_active' => 'Aktif'],
            ],
            'roles' => [
                $master->table('roles')->where('company_id', $companyId)
                    ->select(['id', 'name', 'guard_name']),
                ['name' => 'Rol', 'guard_name' => 'Koruma'],
            ],
            'audit' => [
                $master->table('activity_log')
                    ->where('subject_type', 'App\Models\Company')
                    ->where('subject_id', $companyId)
                    ->select(['id', 'description', 'event', 'created_at']),
                ['description' => 'İşlem', 'event' => 'Olay', 'created_at' => 'Zaman'],
            ],
            'print-profiles' => [
                $master->table('print_profiles')->where('company_id', $companyId)
                    ->select(['id', 'print_type', 'printer_name', 'paper_code', 'width_mm', 'height_mm']),
                ['print_type' => 'Çıktı Tipi', 'printer_name' => 'Yazıcı', 'paper_code' => 'Kağıt', 'width_mm' => 'Genişlik (mm)', 'height_mm' => 'Yükseklik (mm)'],
            ],
            'company-copy-permissions' => [
                $master->table('company_copy_permissions as ccp')
                    ->join('companies as target', 'target.id', '=', 'ccp.target_company_id')
                    ->where('ccp.source_company_id', $companyId)
                    ->select(['ccp.id', 'target.name as target_name', 'ccp.type', 'ccp.is_active']),
                ['target_name' => 'Hedef Şirket', 'type' => 'İzin Türü', 'is_active' => 'Aktif'],
            ],
        };

        $rows = $query->orderByDesc($this->section === 'users' ? 'u.id' : ($this->section === 'company-copy-permissions' ? 'ccp.id' : 'id'))->paginate(25);

        return view('livewire.pages.settings.master-settings-index', [
            'columns' => $columns,
            'rows' => $rows,
            'description' => $this->section === 'audit'
                ? 'Yalnız aktif şirketi doğrudan konu alan Master işlem kayıtları gösterilir.'
                : 'Aktif şirket kapsamındaki kayıtlar. Bu görünüm salt okunurdur.',
        ])->layout('layouts.app', [
            'pageTitle' => $config['title'],
        ]);
    }
}
