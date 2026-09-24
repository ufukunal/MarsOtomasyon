using Mars.Application.Sales;
using Mars.Domain.Sales;

internal static class SalesImp001Tests
{
    public static IReadOnlyList<(string Name, Action Test)> Cases { get; } =
    [
        ("SALES-IMP-001 calculation uses frozen discount/tax/rounding order", CalculationUsesFrozenOrder),
        ("SALES-IMP-001 document discount residual is deterministic", DocumentDiscountResidualIsDeterministic),
        ("SALES-IMP-001 tranche does not expose deferred Invoice or Warehouse work permissions", DeferredPermissionsAreAbsent)
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
