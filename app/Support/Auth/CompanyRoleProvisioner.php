<?php

namespace App\Support\Auth;

use App\Models\Company;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class CompanyRoleProvisioner
{
    private const SCREENS = [
        'companies',
        'users',
        'roles',
        'periods',
        'audit',
        'print_profiles',
        'company_copy_permissions',
        'locations',
        'units',
        'product_categories',
        'brands',
        'contacts',
        'products',
        'variant_groups',
        'price_lists',
        'imports',
        'stock',
        'transfers',
        'warehouse_slips',
        'stock_counts',
        'quarantine',
        'reservations',
        'quotes',
        'sales_orders',
        'dispatches',
        'sales_invoices',
        'proformas',
        'collections',
        'contact_aging',
        'cash_accounts',
        'bank_accounts',
        'purchase_orders',
        'goods_receipts',
        'supplier_invoices',
        'payments',
        'supplier_performance',
        'finance_movements',
        'finance_transfers',
        'expenses',
        'advances',
        'securities',
        'security_payrolls',
        'bank_statements',
        'bank_reconciliation',
        'returns',
        'import_shipments',
        'production_recipes',
        'production_orders',
        'subcontracting',
        'channel_accounts',
        'channel_listings',
        'channel_sync',
    ];

    private const ACTIONS = [
        'view',
        'create',
        'update',
        'cancel',
    ];

    /**
     * @return array<string, Role>
     */
    public function handle(Company $company): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allScreenPermissions = [];

        foreach (self::SCREENS as $screen) {
            foreach (self::ACTIONS as $action) {
                $allScreenPermissions[] = "{$screen}.{$action}";
            }
        }

        $permissionNames = [
            ...$allScreenPermissions,
            'cost.view',
            'periods.reopen',
            'contacts.sensitive.view',
            'sales.quote.approve',
            'purchase_orders.approve',
            'import_shipments.receive',
            'import_shipments.close',
            'reports.view',
            'reports.consolidated',
            'reports.presets.share',
            'document_templates.view',
            'document_templates.update',
        ];

        foreach ($permissionNames as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $viewerPermissions = [
            ...array_values(array_filter(
                $allScreenPermissions,
                fn (string $name): bool => str_ends_with($name, '.view'),
            )),
            'reports.view',
        ];

        $technicalViews = [
            'units.view',
            'product_categories.view',
            'brands.view',
            'variant_groups.view',
        ];

        $salesDocumentViews = [
            'quotes.view',
            'sales_orders.view',
            'dispatches.view',
            'sales_invoices.view',
            'proformas.view',
        ];

        $roleMatrix = [
            'Yönetici' => $permissionNames,
            'Muhasebe' => [
                'reports.view', 'reports.consolidated', 'reports.presets.share',
                'document_templates.view', 'document_templates.update',
                'periods.view', 'periods.create', 'periods.update', 'periods.cancel',
                'audit.view',
                'print_profiles.view', 'print_profiles.create', 'print_profiles.update', 'print_profiles.cancel',
                'contacts.view', 'contacts.create', 'contacts.update',
                'products.view', 'locations.view', 'price_lists.view', 'stock.view', 'cost.view',
                ...$salesDocumentViews,
                'sales_invoices.create', 'sales_invoices.update', 'sales_invoices.cancel',
                'collections.view', 'collections.create', 'collections.cancel',
                'contact_aging.view',
                'cash_accounts.view', 'cash_accounts.create', 'cash_accounts.update',
                'bank_accounts.view', 'bank_accounts.create', 'bank_accounts.update',
                'purchase_orders.view', 'goods_receipts.view',
                'supplier_invoices.view', 'supplier_invoices.create', 'supplier_invoices.update', 'supplier_invoices.cancel',
                'payments.view', 'payments.create', 'payments.cancel',
                'supplier_performance.view',
                'finance_movements.view', 'finance_movements.create', 'finance_movements.cancel',
                'finance_transfers.view', 'finance_transfers.create', 'finance_transfers.cancel',
                'expenses.view', 'expenses.create', 'expenses.cancel',
                'advances.view', 'advances.create', 'advances.cancel',
                'securities.view', 'securities.create', 'securities.update', 'securities.cancel',
                'security_payrolls.view', 'security_payrolls.create', 'security_payrolls.cancel',
                'bank_statements.view', 'bank_statements.create',
                'bank_reconciliation.view', 'bank_reconciliation.update',
                'returns.view', 'returns.create', 'returns.update', 'returns.cancel',
                'import_shipments.view', 'import_shipments.create', 'import_shipments.update', 'import_shipments.close',
                'production_orders.view', 'subcontracting.view',
                'channel_accounts.view', 'channel_sync.view',
                ...$technicalViews,
            ],
            'Satış' => [
                'reports.view',
                'print_profiles.view',
                'contacts.view', 'contacts.create', 'contacts.update',
                'products.view', 'locations.view', 'price_lists.view', 'stock.view',
                'quotes.view', 'quotes.create', 'quotes.update', 'quotes.cancel',
                'sales.quote.approve',
                'sales_orders.view', 'sales_orders.create', 'sales_orders.update', 'sales_orders.cancel',
                'dispatches.view', 'dispatches.create', 'dispatches.update', 'dispatches.cancel',
                'sales_invoices.view', 'sales_invoices.create', 'sales_invoices.update', 'sales_invoices.cancel',
                'proformas.view', 'proformas.create',
                'collections.view', 'collections.create',
                'contact_aging.view',
                'reservations.view', 'reservations.create', 'reservations.update',
                'returns.view', 'returns.create', 'returns.update',
                'production_orders.view',
                'channel_accounts.view',
                'channel_listings.view', 'channel_listings.create', 'channel_listings.update', 'channel_listings.cancel',
                'channel_sync.view', 'channel_sync.update',
                ...$technicalViews,
            ],
            'Satınalma' => [
                'reports.view',
                'print_profiles.view', 'cost.view',
                'contacts.view', 'contacts.create', 'contacts.update',
                'products.view', 'locations.view', 'stock.view',
                'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update', 'purchase_orders.cancel',
                'goods_receipts.view',
                'supplier_invoices.view', 'supplier_invoices.create', 'supplier_invoices.update',
                'supplier_performance.view',
                'returns.view', 'returns.create', 'returns.update',
                'import_shipments.view', 'import_shipments.create', 'import_shipments.update', 'import_shipments.close',
                'production_orders.view',
                'subcontracting.view', 'subcontracting.update',
                ...$technicalViews,
            ],
            'Depo' => [
                'reports.view',
                'print_profiles.view',
                'contacts.view', 'products.view',
                'locations.view', 'locations.create', 'locations.update', 'stock.view',
                'transfers.view', 'transfers.create', 'transfers.update', 'transfers.cancel',
                'warehouse_slips.view', 'warehouse_slips.create', 'warehouse_slips.update', 'warehouse_slips.cancel',
                'stock_counts.view', 'stock_counts.create', 'stock_counts.update', 'stock_counts.cancel',
                'quarantine.view', 'quarantine.update',
                'reservations.view', 'reservations.create', 'reservations.update',
                'dispatches.view', 'dispatches.create', 'dispatches.update',
                'purchase_orders.view',
                'goods_receipts.view', 'goods_receipts.create', 'goods_receipts.update', 'goods_receipts.cancel',
                'returns.view',
                'import_shipments.view', 'import_shipments.receive',
                'production_orders.view',
                'subcontracting.view', 'subcontracting.create', 'subcontracting.update',
                'channel_listings.view', 'channel_sync.view',
                ...$technicalViews,
            ],
            'Üretim' => [
                'reports.view',
                'print_profiles.view', 'cost.view',
                'contacts.view', 'products.view', 'locations.view', 'stock.view',
                'transfers.view', 'transfers.create', 'transfers.update', 'transfers.cancel',
                'production_recipes.view', 'production_recipes.create', 'production_recipes.update', 'production_recipes.cancel',
                'production_orders.view', 'production_orders.create', 'production_orders.update', 'production_orders.cancel',
                'subcontracting.view', 'subcontracting.create', 'subcontracting.update', 'subcontracting.cancel',
                ...$technicalViews,
            ],
            'Görüntüleyici' => $viewerPermissions,
        ];
        setPermissionsTeamId($company->id);

        $roles = [];

        foreach ($roleMatrix as $roleName => $rolePermissions) {
            $role = Role::query()->firstOrCreate([
                'company_id' => $company->id,
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($rolePermissions);
            $roles[$roleName] = $role;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $roles;
    }
}
