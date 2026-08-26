<?php

namespace App\Modules\Core\Enums;

enum PermissionKey: string
{
    case CompanyView = 'core.company.view';
    case CompanyManage = 'core.company.manage';
    case BranchView = 'core.branch.view';
    case BranchManage = 'core.branch.manage';
    case UserView = 'core.user.view';
    case UserManage = 'core.user.manage';
    case RoleView = 'core.role.view';
    case RoleManage = 'core.role.manage';
    case SettingsView = 'core.settings.view';
    case SettingsManage = 'core.settings.manage';
    case FileView = 'core.file.view';
    case FileManage = 'core.file.manage';
    case AccountView = 'accounts.view';
    case AccountManage = 'accounts.manage';
    case ProductView = 'products.view';
    case ProductManage = 'products.manage';
    case InventoryView = 'inventory.view';
    case InventoryManage = 'inventory.manage';
    case QuoteView = 'quotes.view';
    case QuoteManage = 'quotes.manage';
    case QuoteApprove = 'quotes.approve';
    case SalesOrderView = 'sales_orders.view';
    case SalesOrderManage = 'sales_orders.manage';
    case DispatchView = 'dispatches.view';
    case DispatchManage = 'dispatches.manage';
    case SalesInvoiceView = 'sales_invoices.view';
    case SalesInvoiceManage = 'sales_invoices.manage';

    public function label(): string
    {
        return match ($this) {
            self::CompanyView => 'Şirket görüntüleme',
            self::CompanyManage => 'Şirket yönetimi',
            self::BranchView => 'Şube görüntüleme',
            self::BranchManage => 'Şube yönetimi',
            self::UserView => 'Kullanıcı görüntüleme',
            self::UserManage => 'Kullanıcı yönetimi',
            self::RoleView => 'Rol ve yetki görüntüleme',
            self::RoleManage => 'Rol ve yetki yönetimi',
            self::SettingsView => 'Ayarları görüntüleme',
            self::SettingsManage => 'Ayarları yönetme',
            self::FileView => 'Dosya görüntüleme',
            self::FileManage => 'Dosya yönetimi',
            self::AccountView => 'Cari görüntüleme',
            self::AccountManage => 'Cari yönetimi',
            self::ProductView => 'Ürün görüntüleme',
            self::ProductManage => 'Ürün yönetimi',
            self::InventoryView => 'Stok ve depo görüntüleme',
            self::InventoryManage => 'Stok ve depo yönetimi',
            self::QuoteView => 'Teklif görüntüleme',
            self::QuoteManage => 'Teklif yönetimi',
            self::QuoteApprove => 'Teklif ticari onayı ve dönüşümü',
            self::SalesOrderView => 'Satış siparişi görüntüleme',
            self::SalesOrderManage => 'Satış siparişi yönetimi',
            self::DispatchView => 'İrsaliye görüntüleme',
            self::DispatchManage => 'İrsaliye yönetimi',
            self::SalesInvoiceView => 'Satış faturası görüntüleme',
            self::SalesInvoiceManage => 'Satış faturası yönetimi',
        };
    }
}
