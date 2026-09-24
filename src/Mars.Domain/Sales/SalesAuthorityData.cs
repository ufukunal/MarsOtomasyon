namespace Mars.Domain.Sales;

public enum QuoteState
{
    Draft = 1,
    PendingInternalApproval = 2,
    CustomerReview = 3,
    Accepted = 4,
    PartiallyConverted = 5,
    Converted = 6,
    Superseded = 7,
    Expired = 8,
    Cancelled = 9
}

public enum SalesOrderState
{
    Draft = 1,
    PendingApproval = 2,
    Confirmed = 3,
    OnHold = 4,
    PartiallyCompleted = 5,
    Completed = 6,
    CancelledRemainder = 7,
    Cancelled = 8
}

public enum SalesOrderAmendmentState
{
    Draft = 1,
    PendingApproval = 2,
    Active = 3,
    Cancelled = 4
}

public enum DispatchState
{
    Draft = 1,
    Ready = 2,
    Posted = 3,
    HandedOver = 4,
    Delivered = 5,
    Cancelled = 6,
    Reversed = 7
}

public enum SalesInvoiceState
{
    Draft = 1,
    Cancelled = 2
}

public enum SalesInvoiceSourceMode
{
    Dispatch = 1,
    Order = 2,
    Direct = 3
}

public sealed record SalesCommercialLineInput(
    int Sequence,
    decimal Quantity,
    decimal UnitPrice,
    decimal LineDiscountPercent,
    decimal TaxPercent);

public sealed record SalesCommercialLineCalculation(
    int Sequence,
    decimal Quantity,
    decimal UnitPrice,
    decimal GrossExtension,
    decimal LineDiscount,
    decimal DocumentDiscount,
    decimal TaxableBase,
    decimal Tax,
    decimal LineTotal);

public sealed record SalesCommercialCalculation(
    IReadOnlyList<SalesCommercialLineCalculation> Lines,
    decimal NetTotal,
    decimal TaxTotal,
    decimal GrossTotal,
    decimal DocumentDiscountTotal);

public static class SalesCommercialCalculator
{
    public static SalesCommercialCalculation Calculate(
        IReadOnlyList<SalesCommercialLineInput> lines,
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
            if (line.Sequence <= 0)
                throw new ArgumentOutOfRangeException(nameof(lines), "Line sequence must be positive.");
            if (line.Quantity <= 0m)
                throw new ArgumentOutOfRangeException(nameof(lines), "Line quantity must be positive.");
            if (line.UnitPrice < 0m)
                throw new ArgumentOutOfRangeException(nameof(lines), "Unit price cannot be negative.");
            if (line.LineDiscountPercent < 0m || line.LineDiscountPercent > 100m)
                throw new ArgumentOutOfRangeException(nameof(lines), "Line discount must be between 0 and 100.");
            if (line.TaxPercent < 0m || line.TaxPercent > 100m)
                throw new ArgumentOutOfRangeException(nameof(lines), "Tax percent must be between 0 and 100.");

            var extension = checked(line.Quantity * line.UnitPrice);
            var lineDiscount = checked(extension * line.LineDiscountPercent / 100m);
            var afterLineDiscount = extension - lineDiscount;
            return new Prepared(index, line, extension, lineDiscount, afterLineDiscount);
        }).ToArray();

        var allocationBase = prepared.Sum(x => x.AfterLineDiscount);
        var documentDiscountTotal = Round(
            allocationBase * documentDiscountPercent / 100m,
            minorUnit);

        var allocations = new decimal[prepared.Length];
        if (documentDiscountTotal != 0m && allocationBase != 0m)
        {
            for (var i = 0; i < prepared.Length; i++)
            {
                allocations[i] = Round(
                    documentDiscountTotal * prepared[i].AfterLineDiscount / allocationBase,
                    minorUnit);
            }

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
            var taxableBase = Round(
                item.AfterLineDiscount - allocations[item.Index],
                minorUnit);
            var tax = Round(taxableBase * item.Input.TaxPercent / 100m, minorUnit);
            var total = taxableBase + tax;

            return new SalesCommercialLineCalculation(
                item.Input.Sequence,
                item.Input.Quantity,
                item.Input.UnitPrice,
                item.Extension,
                item.LineDiscount,
                allocations[item.Index],
                taxableBase,
                tax,
                total);
        }).OrderBy(x => x.Sequence).ToArray();

        return new SalesCommercialCalculation(
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
        SalesCommercialLineInput Input,
        decimal Extension,
        decimal LineDiscount,
        decimal AfterLineDiscount);
}
