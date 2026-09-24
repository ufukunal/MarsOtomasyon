using Mars.Application.Purchasing;
using Mars.Domain.Purchasing;
using Mars.Infrastructure.Persistence;
using Mars.Infrastructure.Persistence.Purchasing;

internal static class PurchasingImp001Tests
{
    public static IReadOnlyList<(string Name, Action Test)> Cases { get; } =
    [
        ("PURCHASING-IMP-001 calculation uses frozen discount tax rounding order", CalculationUsesFrozenOrder),
        ("PURCHASING-IMP-001 permissions exclude Supplier Invoice posting authority", PermissionsExcludeInvoicePosting),
        ("PURCHASING-IMP-001 model contains normalized authority tables", ModelContainsAuthorityTables),
        ("PURCHASING-IMP-001 persistence implements broad authority contract", PersistenceImplementsContract)
    ];

    private static void CalculationUsesFrozenOrder()
    {
        var calc = PurchaseCommercialCalculator.Calculate(
            [
                new PurchaseCommercialLineInput(1, 2m, 100m, 10m, 20m),
                new PurchaseCommercialLineInput(2, 1m, 50m, 0m, 10m)
            ],
            10m,
            2);

        AssertEqual(23m, calc.DocumentDiscountTotal);
        AssertEqual(207m, calc.NetTotal);
        AssertEqual(36.9m, calc.TaxTotal);
        AssertEqual(243.9m, calc.GrossTotal);
    }

    private static void PermissionsExcludeInvoicePosting()
    {
        AssertTrue(PurchasingPermissions.All.Contains(PurchasingPermissions.InvoiceCreate));
        AssertTrue(PurchasingPermissions.All.Contains(PurchasingPermissions.InvoiceDirectCreate));
        AssertTrue(!PurchasingPermissions.All.Contains("purchasing.invoice.post"));
        AssertTrue(!PurchasingPermissions.All.Contains("purchasing.invoice.reverse"));
    }

    private static void ModelContainsAuthorityTables()
    {
        var options = MarsDbContextOptions.CreateRuntime(
            new PostgreSqlRuntimeOptions("Host=localhost;Database=mars_purchasing_imp_001_probe"));
        using var context = new MarsDbContext(options);
        var tables = context.Model.GetEntityTypes()
            .Select(x => $"{x.GetSchema()}.{x.GetTableName()}")
            .ToHashSet(StringComparer.Ordinal);

        foreach (var table in new[]
        {
            "purchasing.purchase_orders",
            "purchasing.purchase_order_versions",
            "purchasing.purchase_order_lines",
            "purchasing.purchase_order_amendments",
            "purchasing.purchase_order_amendment_deltas",
            "purchasing.goods_receipts",
            "purchasing.goods_receipt_lines",
            "purchasing.goods_receipt_inventory_effect_links",
            "purchasing.supplier_invoices",
            "purchasing.supplier_invoice_lines",
            "purchasing.supplier_invoice_source_links",
            "purchasing.purchase_match_results",
            "purchasing.purchase_match_exceptions"
        })
        {
            AssertTrue(tables.Contains(table));
        }
    }

    private static void PersistenceImplementsContract()
    {
        AssertTrue(typeof(IPurchasingPersistence).IsAssignableFrom(typeof(EfPurchasingPersistence)));
        AssertTrue(typeof(IPurchasingTransactionCoordinator).IsAssignableFrom(typeof(EfPurchasingTransactionCoordinator)));
    }

    private static void AssertTrue(bool value)
    {
        if (!value) throw new InvalidOperationException("Assertion failed.");
    }

    private static void AssertEqual<T>(T expected, T actual)
    {
        if (!EqualityComparer<T>.Default.Equals(expected, actual))
            throw new InvalidOperationException($"Expected '{expected}', actual '{actual}'.");
    }
}
