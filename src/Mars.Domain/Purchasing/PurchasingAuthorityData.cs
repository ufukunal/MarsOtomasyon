namespace Mars.Domain.Purchasing;

public enum PurchaseOrderState
{
    Draft = 1,
    PendingApproval = 2,
    Confirmed = 3,
    OnHold = 4,
    PartiallyReceived = 5,
    Completed = 6,
    CancelledRemainder = 7,
    Cancelled = 8
}

public enum PurchaseOrderAmendmentState
{
    Draft = 1,
    Active = 2,
    Cancelled = 3
}

public enum GoodsReceiptState
{
    Draft = 1,
    Ready = 2,
    Posted = 3,
    Reversed = 4,
    Cancelled = 5
}

public enum SupplierInvoiceState
{
    Draft = 1,
    Cancelled = 2
}

public enum SupplierInvoiceSourceMode
{
    GoodsReceipt = 1,
    PurchaseOrder = 2,
    Direct = 3
}

public enum PurchaseMatchKind
{
    TwoWay = 1,
    ThreeWay = 2,
    Direct = 3
}

public enum PurchaseMatchState
{
    Pending = 1,
    Matched = 2,
    Blocked = 3,
    ExceptionRequired = 4,
    ExceptionApproved = 5
}

public sealed record PurchaseCommercialLineInput(
    int Sequence,
    decimal Quantity,
    decimal UnitPrice,
    decimal LineDiscountPercent,
    decimal TaxPercent);

public sealed record PurchaseCommercialLineCalculation(
    int Sequence,
    decimal Quantity,
    decimal UnitPrice,
    decimal GrossExtension,
    decimal LineDiscount,
    decimal DocumentDiscount,
    decimal TaxableBase,
    decimal Tax,
    decimal LineTotal);

public sealed record PurchaseCommercialCalculation(
    IReadOnlyList<PurchaseCommercialLineCalculation> Lines,
    decimal NetTotal,
    decimal TaxTotal,
    decimal GrossTotal,
    decimal DocumentDiscountTotal);

public static class PurchaseCommercialCalculator
{
    public static PurchaseCommercialCalculation Calculate(
        IReadOnlyList<PurchaseCommercialLineInput> lines,
        decimal documentDiscountPercent,
        int minorUnit)
    {
        ArgumentNullException.ThrowIfNull(lines);
        if (lines.Count == 0)
            throw new ArgumentException("At least one line is required.", nameof(lines));
        if (documentDiscountPercent < 0m || documentDiscountPercent > 100m)
            throw new ArgumentOutOfRangeException(nameof(documentDiscountPercent));
        if (minorUnit < 0 || minorUnit > 6)
            throw new ArgumentOutOfRangeException(nameof(minorUnit));

        var prepared = lines.Select((line, index) =>
        {
            if (line.Sequence <= 0 || line.Quantity <= 0m)
                throw new ArgumentOutOfRangeException(nameof(lines), "Sequence and quantity must be positive.");
            if (line.UnitPrice < 0m)
                throw new ArgumentOutOfRangeException(nameof(lines), "Unit price cannot be negative.");
            if (line.LineDiscountPercent < 0m || line.LineDiscountPercent > 100m)
                throw new ArgumentOutOfRangeException(nameof(lines), "Line discount must be between 0 and 100.");
            if (line.TaxPercent < 0m || line.TaxPercent > 100m)
                throw new ArgumentOutOfRangeException(nameof(lines), "Tax percent must be between 0 and 100.");

            var extension = checked(line.Quantity * line.UnitPrice);
            var lineDiscount = checked(extension * line.LineDiscountPercent / 100m);
            return new Prepared(index, line, extension, lineDiscount, extension - lineDiscount);
        }).ToArray();

        var allocationBase = prepared.Sum(x => x.AfterLineDiscount);
        var documentDiscountTotal = Round(allocationBase * documentDiscountPercent / 100m, minorUnit);
        var allocations = new decimal[prepared.Length];

        if (documentDiscountTotal != 0m && allocationBase != 0m)
        {
            for (var i = 0; i < prepared.Length; i++)
                allocations[i] = Round(documentDiscountTotal * prepared[i].AfterLineDiscount / allocationBase, minorUnit);

            var residual = documentDiscountTotal - allocations.Sum();
            if (residual != 0m)
            {
                var target = prepared
                    .OrderByDescending(x => x.AfterLineDiscount)
                    .ThenBy(x => x.Input.Sequence)
                    .ThenBy(x => x.Index)
                    .First();
                allocations[target.Index] += residual;
            }
        }

        var calculated = prepared.Select(item =>
        {
            var taxableBase = Round(item.AfterLineDiscount - allocations[item.Index], minorUnit);
            var tax = Round(taxableBase * item.Input.TaxPercent / 100m, minorUnit);
            return new PurchaseCommercialLineCalculation(
                item.Input.Sequence,
                item.Input.Quantity,
                item.Input.UnitPrice,
                item.Extension,
                item.LineDiscount,
                allocations[item.Index],
                taxableBase,
                tax,
                taxableBase + tax);
        }).OrderBy(x => x.Sequence).ToArray();

        return new PurchaseCommercialCalculation(
            calculated,
            calculated.Sum(x => x.TaxableBase),
            calculated.Sum(x => x.Tax),
            calculated.Sum(x => x.LineTotal),
            documentDiscountTotal);
    }

    private static decimal Round(decimal value, int minorUnit) =>
        Math.Round(value, minorUnit, MidpointRounding.AwayFromZero);

    private sealed record Prepared(
        int Index,
        PurchaseCommercialLineInput Input,
        decimal Extension,
        decimal LineDiscount,
        decimal AfterLineDiscount);
}
