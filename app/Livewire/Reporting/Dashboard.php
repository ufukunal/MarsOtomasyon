<?php

namespace App\Livewire\Reporting;

use App\Models\Company;
use App\Models\Period;
use App\Support\Period\PeriodContext;
use App\Support\Reporting\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class Dashboard extends Component
{
    public function mount(): void
    {
        PeriodContext::ensure();
    }

    public function render(): View
    {
        // Quick actions mirror v65; authorization and real Laravel routes remain authoritative.
        $actions = [
            ['title' => 'Satış Faturaları', 'description' => 'Müşteriye fatura oluştur', 'icon' => '🧾', 'route' => 'sales.invoices.edit', 'permission' => 'sales_invoices.create'],
            ['title' => 'Tahsilat Gir', 'description' => 'Müşteriden alınan ödeme', 'icon' => '₺', 'route' => 'finance.collections.create', 'permission' => 'collections.create'],
            ['title' => 'Alış Faturaları', 'description' => 'Tedarikçi faturası kaydet', 'icon' => '📥', 'route' => 'purchases.invoices.edit', 'permission' => 'supplier_invoices.create'],
            ['title' => 'Ödeme Yap', 'description' => 'Tedarikçiye ödeme kaydet', 'icon' => '↗', 'route' => 'purchases.payments.create', 'permission' => 'payments.create'],
            ['title' => 'Satış Siparişleri', 'description' => 'Yeni satış siparişi', 'icon' => '📋', 'route' => 'sales.orders.edit', 'permission' => 'sales_orders.create'],
            ['title' => 'Ürün Ekle', 'description' => 'Yeni stok kartı', 'icon' => '📦', 'route' => 'products.create', 'permission' => 'products.create'],
            ['title' => 'Cari Ekle', 'description' => 'Müşteri veya tedarikçi', 'icon' => '●', 'route' => 'contacts.create', 'permission' => 'contacts.create'],
            ['title' => 'Cari Yaşlandırma', 'description' => 'Alacak vadelerini görüntüle', 'icon' => '▥', 'route' => 'finance.contact-aging', 'permission' => 'contact_aging.view'],
        ];

        $actor = auth()->user();
        $period = Period::query()->findOrFail(PeriodContext::periodId());

        return view('livewire.reporting.dashboard', [
            'widgets' => app(DashboardService::class)->widgets(),
            'quickActions' => array_values(array_filter(
                $actions,
                static fn (array $action): bool => $actor !== null && $actor->can($action['permission']),
            )),
            'companyName' => Company::query()->find(PeriodContext::companyId())?->name,
            'periodOpen' => $period->status === 'active',
        ])->layout('layouts.app', [
            'pageTitle' => 'Ana Sayfa',
        ]);
    }
}
