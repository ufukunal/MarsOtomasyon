<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\ImportErrorReportController;
use App\Http\Controllers\ProductImageController;
use App\Livewire\Pages\Auth\ForgotPassword;
use App\Livewire\Pages\Auth\Login;
use App\Livewire\Pages\Auth\PeriodSelection;
use App\Livewire\Pages\Auth\ResetPassword;
use App\Livewire\Pages\Catalog\BrandForm;
use App\Livewire\Pages\Catalog\BrandList;
use App\Livewire\Pages\Catalog\CategoryForm;
use App\Livewire\Pages\Catalog\CategoryList;
use App\Livewire\Pages\Catalog\LocationForm;
use App\Livewire\Pages\Catalog\LocationList;
use App\Livewire\Pages\Catalog\UnitForm;
use App\Livewire\Pages\Catalog\UnitList;
use App\Livewire\Pages\Companies\CrossCompanyCopy;
use App\Livewire\Pages\Contacts\ContactForm;
use App\Livewire\Pages\Contacts\ContactList;
use App\Livewire\Pages\Import\ImportWizard;
use App\Livewire\Pages\Pricing\PriceListDetail;
use App\Livewire\Pages\Pricing\PriceListList;
use App\Livewire\Pages\Products\ProductForm;
use App\Livewire\Pages\Products\ProductList;
use App\Livewire\Pages\Products\VariantGroupDetail;
use App\Livewire\Pages\Settings\IntegrityReport;
use App\Livewire\Pages\Settings\Periods;
use App\Livewire\Pages\Setup\CompanyWizard;
use App\Livewire\Pages\Stock\StockMovements;
use App\Livewire\Pages\Stock\StockStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/saglik', HealthController::class)
    ->middleware('local.network')
    ->name('health');

Route::view('/', 'welcome')->middleware('auth')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/giris', Login::class)->name('login');
    Route::get('/parola-unuttum', ForgotPassword::class)->name('password.request');
    Route::get('/parola-sifirla/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/secim', PeriodSelection::class)->name('period.select');
    Route::get('/ayarlar/donemler', Periods::class)->name('settings.periods');
    Route::get('/kartlar/lokasyonlar', LocationList::class)->name('locations.index');
    Route::get('/kartlar/lokasyonlar/yeni', LocationForm::class)->name('locations.create');
    Route::get('/kartlar/lokasyonlar/{location}', LocationForm::class)->name('locations.edit');
    Route::get('/kartlar/birimler', UnitList::class)->name('units.index');
    Route::get('/kartlar/birimler/yeni', UnitForm::class)->name('units.create');
    Route::get('/kartlar/birimler/{unit}', UnitForm::class)->name('units.edit');
    Route::get('/kartlar/kategoriler', CategoryList::class)->name('categories.index');
    Route::get('/kartlar/kategoriler/yeni', CategoryForm::class)->name('categories.create');
    Route::get('/kartlar/kategoriler/{category}', CategoryForm::class)->name('categories.edit');
    Route::get('/kartlar/markalar', BrandList::class)->name('brands.index');
    Route::get('/kartlar/markalar/yeni', BrandForm::class)->name('brands.create');
    Route::get('/kartlar/markalar/{brand}', BrandForm::class)->name('brands.edit');
    Route::get('/kartlar/cariler', ContactList::class)->name('contacts.index');
    Route::get('/kartlar/cariler/yeni', ContactForm::class)->name('contacts.create');
    Route::get('/kartlar/cariler/{contact}', ContactForm::class)->name('contacts.edit');
    Route::get('/kartlar/urunler', ProductList::class)->name('products.index');
    Route::get('/kartlar/urunler/yeni', ProductForm::class)->name('products.create');
    Route::get('/kartlar/urunler/{product}', ProductForm::class)->name('products.edit');
    Route::get('/kartlar/varyant-gruplari/{group?}', VariantGroupDetail::class)->name('variant-groups.detail');
    Route::get('/kartlar/fiyat-listeleri', PriceListList::class)->name('price-lists.index');
    Route::get('/kartlar/fiyat-listeleri/{list?}', PriceListDetail::class)->name('price-lists.detail');
    Route::get('/kartlar/baska-sirketten-aktar', CrossCompanyCopy::class)->name('company-copy.index');
    Route::get('/ice-aktarma', ImportWizard::class)->name('imports.index');
    Route::get('/stok/durum', StockStatus::class)->name('stock.status');
    Route::get('/stok/hareketler', StockMovements::class)->name('stock.movements');
    Route::get('/ice-aktarma/{batchId}/hatalar.xlsx', ImportErrorReportController::class)->name('imports.errors');
    Route::get('/urunler/{product}/gorseller/{attachment}', ProductImageController::class)->name('products.images.show');
    Route::get('/ayarlar/butunluk', IntegrityReport::class)->name('settings.integrity');

    Route::post('/cikis', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});

Route::get('/kurulum', CompanyWizard::class)->name('setup');
