using Mars.Application.Foundation.Approvals;
using Mars.Application.Foundation.Context;
using Mars.Application.Sales;
using Mars.Infrastructure.Persistence;
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
        ("SALES-IMP-001 model contains approval and Warehouse access scope primitives", ModelContainsSupportingPrimitives)
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
        var authority = new ApprovalDecisionAuthority(new FakeApprovalPersistence());
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
