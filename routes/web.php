<?php

use App\Http\Controllers\ChannelAssetController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HepsiburadaWebhookController;
use App\Http\Controllers\ImportErrorReportController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ReportExportDownloadController;
use App\Http\Controllers\TrendyolWebhookController;
use App\Http\Controllers\WooCommerceWebhookController;
use App\Livewire\Finance\BankStatementCenter;
use App\Livewire\Finance\CollectionForm;
use App\Livewire\Finance\ContactAging;
use App\Livewire\Finance\ContactDebitCreditForm;
use App\Livewire\Finance\FinanceAccounts;
use App\Livewire\Finance\FinanceOperationCenter;
use App\Livewire\Channels\ChannelAccountCenter;
use App\Livewire\Channels\ChannelListingCenter;
use App\Livewire\Channels\ChannelSyncCenter;
use App\Livewire\Finance\SecuritiesCenter;
use App\Livewire\Imports\ImportCenter;
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
use App\Livewire\Pages\Stock\QuarantineControl;
use App\Livewire\Pages\Stock\ReservationList;
use App\Livewire\Pages\Stock\StockCountDetail;
use App\Livewire\Pages\Stock\StockCountList;
use App\Livewire\Pages\Stock\StockMovements;
use App\Livewire\Pages\Stock\StockStatus;
use App\Livewire\Pages\Stock\TransferDetail;
use App\Livewire\Pages\Stock\TransferList;
use App\Livewire\Pages\Stock\WarehouseSlipDetail;
use App\Livewire\Pages\Stock\WarehouseSlipList;
use App\Livewire\Purchases\GoodsReceiptEditor;
use App\Livewire\Purchases\GoodsReceiptList;
use App\Livewire\Purchases\PaymentForm;
use App\Livewire\Purchases\PurchaseOrderEditor;
use App\Livewire\Purchases\PurchaseOrderList;
use App\Livewire\Purchases\SupplierInvoiceEditor;
use App\Livewire\Purchases\SupplierInvoiceList;
use App\Livewire\Purchases\SupplierPerformance;
use App\Livewire\Production\ProductionOrderCenter;
use App\Livewire\Production\RecipeCenter;
use App\Livewire\Production\SubcontractingCenter;
use App\Livewire\Reporting\ConsolidatedReportCenter;
use App\Livewire\Reporting\Dashboard;
use App\Livewire\Reporting\DocumentTemplateDesigner;
use App\Livewire\Reporting\ExportCenter;
use App\Livewire\Reporting\ReportCenter;
use App\Livewire\Returns\ReturnCenter;
use App\Livewire\Sales\DispatchEditor;
use App\Livewire\Sales\DispatchList;
use App\Livewire\Sales\ProformaDetail;
use App\Livewire\Sales\ProformaList;
use App\Livewire\Sales\QuoteEditor;
use App\Livewire\Sales\QuoteList;
use App\Livewire\Sales\SalesInvoiceEditor;
use App\Livewire\Sales\SalesInvoiceList;
use App\Livewire\Sales\SalesOrderEditor;
use App\Livewire\Sales\SalesOrderList;
use App\Livewire\Sales\VehicleHotSale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/saglik', HealthController::class)
    ->middleware('local.network')
    ->name('health');

Route::get('/', Dashboard::class)->middleware('auth')->name('home');

Route::post('/hooks/channel/{account}', TrendyolWebhookController::class)
    ->whereNumber('account')
    ->name('webhooks.trendyol');

Route::put('/hooks/channel/hepsiburada/{account}/{event}', HepsiburadaWebhookController::class)
    ->whereNumber('account')
    ->where('event', 'createOrder|createPackages|orderCancel|unpack|intransit|deliver|undeliver|changeShippingAddressOrder|awaitingAction|awaitingPreApproval|disputedClaimResult|packageFromClaimResult')
    ->name('webhooks.hepsiburada');

Route::post('/hooks/channel/woocommerce/{account}', WooCommerceWebhookController::class)
    ->whereNumber('account')
    ->name('webhooks.woocommerce');

Route::get('/channel-assets/{company}/{period}/{product}/{attachment}', ChannelAssetController::class)
    ->middleware('signed')
    ->whereNumber('company')
    ->whereNumber('period')
    ->whereNumber('product')
    ->whereNumber('attachment')
    ->name('channels.asset');

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

    Route::get('/satis/teklifler', QuoteList::class)->name('sales.quotes.index');
    Route::get('/satis/teklif/{id?}', QuoteEditor::class)->name('sales.quotes.edit');
    Route::get('/satis/siparisler', SalesOrderList::class)->name('sales.orders.index');
    Route::get('/satis/siparis/{id?}', SalesOrderEditor::class)->name('sales.orders.edit');
    Route::get('/satis/irsaliyeler', DispatchList::class)->name('sales.dispatches.index');
    Route::get('/satis/irsaliye/{id?}', DispatchEditor::class)->name('sales.dispatches.edit');
    Route::get('/satis/faturalar', SalesInvoiceList::class)->name('sales.invoices.index');
    Route::get('/satis/fatura/{id?}', SalesInvoiceEditor::class)->name('sales.invoices.edit');
    Route::get('/satis/proformalar', ProformaList::class)->name('sales.proformas.index');
    Route::get('/satis/proforma/{id}', ProformaDetail::class)->name('sales.proformas.show');
    Route::get('/satis/arac-sicak-satis', VehicleHotSale::class)->name('sales.vehicle-hot-sale');

    Route::get('/alis/siparisler', PurchaseOrderList::class)->name('purchases.orders.index');
    Route::get('/alis/siparis/{id?}', PurchaseOrderEditor::class)->name('purchases.orders.edit');
    Route::get('/alis/mal-kabul', GoodsReceiptList::class)->name('purchases.receipts.index');
    Route::get('/alis/mal-kabul/{id}', GoodsReceiptEditor::class)->name('purchases.receipts.edit');
    Route::get('/alis/faturalar', SupplierInvoiceList::class)->name('purchases.invoices.index');
    Route::get('/alis/fatura/{id?}', SupplierInvoiceEditor::class)->name('purchases.invoices.edit');
    Route::get('/alis/odeme', PaymentForm::class)->name('purchases.payments.create');
    Route::get('/alis/tedarikci-performansi', SupplierPerformance::class)->name('purchases.supplier-performance');

    Route::get('/finans/tahsilat', CollectionForm::class)->name('finance.collections.create');
    Route::get('/finans/cari-fis', ContactDebitCreditForm::class)->name('finance.contact-debit-credit');
    Route::get('/finans/yaslandirma', ContactAging::class)->name('finance.contact-aging');
    Route::get('/finans/hesaplar', FinanceAccounts::class)->name('finance.accounts');
    Route::get('/finans/islemler', FinanceOperationCenter::class)->name('finance.operations');
    Route::get('/finans/ekstre-mutabakat', BankStatementCenter::class)->name('finance.bank-statements');
    Route::get('/finans/cek-senet', SecuritiesCenter::class)->name('finance.securities');
    Route::get('/iadeler', ReturnCenter::class)->name('returns.center');
    Route::get('/ithalat', ImportCenter::class)->name('imports.shipments');

    Route::get('/uretim/receteler', RecipeCenter::class)->name('production.recipes');
    Route::get('/uretim/emirler', ProductionOrderCenter::class)->name('production.orders');
    Route::get('/uretim/fason', SubcontractingCenter::class)->name('production.subcontracting');

    Route::get('/e-ticaret/kanal-hesaplari', ChannelAccountCenter::class)->name('channels.accounts');
    Route::get('/e-ticaret/listingler', ChannelListingCenter::class)->name('channels.listings');
    Route::get('/e-ticaret/sync', ChannelSyncCenter::class)->name('channels.sync');

    Route::get('/raporlar', ReportCenter::class)
        ->middleware('throttle:report')
        ->name('reports.center');
    Route::get('/raporlar/cok-donem', ConsolidatedReportCenter::class)
        ->middleware('throttle:report')
        ->name('reports.consolidated');
    Route::get('/raporlar/exportlar', ExportCenter::class)->name('reports.exports');
    Route::get('/raporlar/sablonlar', DocumentTemplateDesigner::class)->name('reports.templates');
    Route::get('/raporlar/exportlar/{export}/indir', ReportExportDownloadController::class)
        ->whereNumber('export')
        ->name('reports.exports.download');

    Route::get('/ice-aktarma', ImportWizard::class)->name('imports.index');
    Route::get('/stok/durum', StockStatus::class)->name('stock.status');
    Route::get('/stok/hareketler', StockMovements::class)->name('stock.movements');
    Route::get('/stok/sayimlar', StockCountList::class)->name('stock.counts.index');
    Route::get('/stok/sayimlar/yeni', StockCountDetail::class)->name('stock.counts.create');
    Route::get('/stok/sayimlar/{id}', StockCountDetail::class)->name('stock.counts.show');
    Route::get('/stok/karantina', QuarantineControl::class)->name('stock.quarantine.index');
    Route::get('/stok/rezervasyonlar', ReservationList::class)->name('stock.reservations.index');
    Route::get('/stok/acilis-bakiyesi', ImportWizard::class)->name('stock.opening.index');
    Route::get('/stok/transferler', TransferList::class)->name('stock.transfers.index');
    Route::get('/stok/transferler/yeni', TransferDetail::class)->name('stock.transfers.create');
    Route::get('/stok/transferler/{id}', TransferDetail::class)->name('stock.transfers.show');
    Route::get('/stok/ambar-fisleri', WarehouseSlipList::class)->name('stock.warehouse-slips.index');
    Route::get('/stok/ambar-fisleri/yeni', WarehouseSlipDetail::class)->name('stock.warehouse-slips.create');
    Route::get('/stok/ambar-fisleri/{id}', WarehouseSlipDetail::class)->name('stock.warehouse-slips.show');
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
