using Mars.Application.Foundation.Approvals;
using Mars.Application.Foundation.Context;
using Mars.Application.Sales;
using Mars.Application.Inventory;
using Mars.Infrastructure.Persistence;
using Mars.Infrastructure.Persistence.Sales;
using Microsoft.EntityFrameworkCore;
using MarsExecutionContext = Mars.Application.Foundation.Context.ExecutionContext;
using Mars.Domain.Sales;

internal static class SalesImp001Tests
{
    public static IReadOnlyList<(string Name, Action Test)> Cases { get; } =
    [
        ("SALES-IMP-001 calculation uses frozen discount/tax/rounding order", CalculationUsesFrozenOrder),
        ("SALES-IMP-001 document discount residual is deterministic", DocumentDiscountResidualIsDeterministic),
        ("SALES-IMP-001 tranche does not expose deferred Invoice or Warehouse work permissions", DeferredPermissionsAreAbsent),
        ("Foundation approval primitive enforces creator approver SoD", ApprovalPrimitiveEnforcesSod),
        ("SALES-IMP-001 model contains approval and Warehouse access scope primitives", ModelContainsSupportingPrimitives),
        ("SALES-IMP-001 model contains normalized Sales authority tables", ModelContainsSalesAuthorityTables),
        ("SALES-IMP-001 persistence implements broad authority contracts", PersistenceImplementsBroadContracts),
        ("Inventory reservation permissions are canonical actions", ReservationPermissionsAreCanonical)
    ];

    private static void CalculationUsesFrozenOrder()
    {
        var result = SalesCommercialCalculator.Calculate(
            [
                new SalesCommercialLineInput(
                    Sequence: 1,
                    Quantity: 2m,
                    UnitPrice: 100m,
                    LineDiscountPercent: 10m,
                    TaxPercent: 20m)
            ],
            documentDiscountPercent: 10m,
            minorUnit: 2);

        var line = result.Lines.Single();
        AssertEqual(200m, line.GrossExtension);
        AssertEqual(20m, line.LineDiscount);
        AssertEqual(18m, line.DocumentDiscount);
        AssertEqual(162m, line.TaxableBase);
        AssertEqual(32.40m, line.Tax);
        AssertEqual(194.40m, line.LineTotal);
        AssertEqual(162m, result.NetTotal);
        AssertEqual(32.40m, result.TaxTotal);
        AssertEqual(194.40m, result.GrossTotal);
    }

    private static void DocumentDiscountResidualIsDeterministic()
    {
        var result = SalesCommercialCalculator.Calculate(
            [
                new SalesCommercialLineInput(1, 1m, 1m, 0m, 0m),
                new SalesCommercialLineInput(2, 1m, 1m, 0m, 0m),
                new SalesCommercialLineInput(3, 1m, 1m, 0m, 0m)
            ],
            documentDiscountPercent: 1m,
            minorUnit: 2);

        AssertEqual(0.03m, result.DocumentDiscountTotal);
        AssertEqual(0.01m, result.Lines[0].DocumentDiscount);
        AssertEqual(0.01m, result.Lines[1].DocumentDiscount);
        AssertEqual(0.01m, result.Lines[2].DocumentDiscount);

        var tie = SalesCommercialCalculator.Calculate(
            [
                new SalesCommercialLineInput(2, 1m, 1m, 0m, 0m),
                new SalesCommercialLineInput(1, 1m, 2m, 0m, 0m)
            ],
            documentDiscountPercent: 1m,
            minorUnit: 2);

        AssertEqual(0.03m, tie.DocumentDiscountTotal);
        AssertEqual(0.02m, tie.Lines.Single(x => x.Sequence == 1).DocumentDiscount);
        AssertEqual(0.01m, tie.Lines.Single(x => x.Sequence == 2).DocumentDiscount);
    }

    private static void DeferredPermissionsAreAbsent()
    {
        AssertTrue(!SalesPermissions.All.Contains("sales.invoice.post", StringComparer.Ordinal));
        AssertTrue(!SalesPermissions.All.Contains("sales.invoice.reverse", StringComparer.Ordinal));
        AssertTrue(!SalesPermissions.All.Contains("sales.dispatch.pick", StringComparer.Ordinal));
        AssertTrue(!SalesPermissions.All.Contains("sales.dispatch.pack", StringComparer.Ordinal));
    }

    private static void ApprovalPrimitiveEnforcesSod()
    {
        var actor = Guid.NewGuid();
        var authority = new ApprovalDecisionAuthority(new FakeSalesApprovalPersistence());
        var context = new MarsExecutionContext(
            actor,
            Guid.NewGuid(),
            null,
            new CorrelationId("corr-sales-approval"));

        var result = authority.DecideAsync(
            new ApprovalDecisionCommand(
                "Sales",
                "QuoteRevision",
                Guid.NewGuid(),
                1,
                actor,
                ApprovalDecisionKind.Approved,
                null,
                "approval-op"),
            context,
            CancellationToken.None).GetAwaiter().GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual("approval.sod.creator_cannot_approve", result.Error?.Code);
    }

    private static void ModelContainsSupportingPrimitives()
    {
        var options = MarsDbContextOptions.CreateRuntime(
            new PostgreSqlRuntimeOptions("Host=localhost;Database=mars_sales_imp_001_model_probe"));
        using var context = new MarsDbContext(options);

        var tables = context.Model.GetEntityTypes()
            .Select(x => $"{x.GetSchema()}.{x.GetTableName()}")
            .ToHashSet(StringComparer.Ordinal);

        AssertTrue(tables.Contains("foundation.approval_decisions"));
        AssertTrue(tables.Contains("inventory.warehouse_access_grants"));

        var grant = context.Model.GetEntityTypes().Single(
            x => x.GetSchema() == "inventory" && x.GetTableName() == "warehouse_access_grants");
        AssertTrue(grant.GetIndexes().Any(x => x.IsUnique));
    }

    private static void ModelContainsSalesAuthorityTables()
    {
        var options = MarsDbContextOptions.CreateRuntime(
            new PostgreSqlRuntimeOptions("Host=localhost;Database=mars_sales_imp_001_model_probe"));
        using var context = new MarsDbContext(options);

        var tables = context.Model.GetEntityTypes()
            .Select(x => $"{x.GetSchema()}.{x.GetTableName()}")
            .ToHashSet(StringComparer.Ordinal);

        foreach (var table in new[]
        {
            "sales.quotes",
            "sales.quote_revisions",
            "sales.quote_lines",
            "sales.quote_conversion_links",
            "sales.sales_orders",
            "sales.sales_order_versions",
            "sales.sales_order_lines",
            "sales.sales_order_amendments",
            "sales.sales_order_amendment_deltas",
            "sales.dispatches",
            "sales.dispatch_lines",
            "sales.dispatch_inventory_effect_links",
            "sales.sales_invoices",
            "sales.sales_invoice_lines",
            "sales.sales_invoice_source_links"
        })
        {
            AssertTrue(tables.Contains(table));
        }
    }

    private static void PersistenceImplementsBroadContracts()
    {
        AssertTrue(typeof(ISalesPersistence).IsAssignableFrom(typeof(EfSalesPersistence)));
        AssertTrue(typeof(ISalesTransactionCoordinator).IsAssignableFrom(typeof(EfSalesTransactionCoordinator)));
        AssertTrue(typeof(EfSalesPersistence).GetMethod(nameof(ISalesPersistence.CreateQuoteAsync)) is not null);
        AssertTrue(typeof(EfSalesPersistence).GetMethod(nameof(ISalesPersistence.ConvertQuoteAsync)) is not null);
        AssertTrue(typeof(EfSalesPersistence).GetMethod(nameof(ISalesPersistence.PrepareDispatchPostAsync)) is not null);
        AssertTrue(typeof(EfSalesPersistence).GetMethod(nameof(ISalesPersistence.CompleteDispatchReverseAsync)) is not null);
        AssertTrue(typeof(EfSalesPersistence).GetMethod(nameof(ISalesPersistence.CreateInvoiceDraftAsync)) is not null);
        AssertTrue(typeof(EfSalesPersistence).GetMethod("PostInvoiceAsync") is null);
        AssertTrue(typeof(EfSalesPersistence).GetMethod("ReverseInvoiceAsync") is null);
    }

    private static void ReservationPermissionsAreCanonical()
    {
        AssertTrue(InventoryPermissions.All.Contains(InventoryPermissions.ReservationCreate, StringComparer.Ordinal));
        AssertTrue(InventoryPermissions.All.Contains(InventoryPermissions.ReservationIncrease, StringComparer.Ordinal));
        AssertTrue(InventoryPermissions.All.Contains(InventoryPermissions.ReservationRelease, StringComparer.Ordinal));
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

internal sealed class FakeSalesApprovalPersistence : IApprovalDecisionPersistence
{
    public Task<ApprovalDecisionPersistenceResult> RecordAsync(
        ApprovalDecisionWrite write,
        CancellationToken cancellationToken) =>
        Task.FromResult(new ApprovalDecisionPersistenceResult(
            ApprovalDecisionPersistenceOutcome.Succeeded,
            write.PublicId));

    public Task<bool> IsApprovedAsync(
        Guid companyId,
        string module,
        string entityType,
        Guid entityPublicId,
        long snapshotVersion,
        CancellationToken cancellationToken) =>
        Task.FromResult(false);
}
