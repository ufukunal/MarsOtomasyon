using Mars.Domain.Sales;

namespace Mars.Api.Sales;

public sealed record SalesTradeLineRequest(
    int Sequence,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal Quantity,
    decimal UnitPrice,
    decimal LineDiscountPercent,
    decimal TaxPercent);

public sealed record CreateQuoteRequest(
    string Number,
    Guid CustomerPartyPublicId,
    string CurrencyCode,
    string? PaymentTerms,
    decimal DocumentDiscountPercent,
    IReadOnlyList<SalesTradeLineRequest> Lines);

public sealed record ReviseQuoteRequest(
    long Version,
    string? PaymentTerms,
    decimal DocumentDiscountPercent,
    IReadOnlyList<SalesTradeLineRequest> Lines);

public sealed record VersionRequest(long Version);
public sealed record ReasonVersionRequest(long Version, string Reason);
public sealed record ReasonRequest(string Reason);
public sealed record ApprovalRequest(string Decision, string? Reason);

public sealed record QuoteConversionLineRequest(Guid QuoteLinePublicId, decimal Quantity);
public sealed record ConvertQuoteRequest(
    long QuoteRevisionNumber,
    string OrderNumber,
    IReadOnlyList<QuoteConversionLineRequest> Lines);

public sealed record CreateDirectOrderRequest(
    string Number,
    Guid CustomerPartyPublicId,
    string CurrencyCode,
    string? PaymentTerms,
    decimal DocumentDiscountPercent,
    IReadOnlyList<SalesTradeLineRequest> Lines);

public sealed record AmendmentDeltaRequest(
    Guid SalesOrderLinePublicId,
    decimal QuantityDelta,
    decimal? NewUnitPrice,
    decimal? NewLineDiscountPercent,
    decimal? NewTaxPercent);

public sealed record CreateAmendmentRequest(
    long Version,
    string? NewPaymentTerms,
    string Reason,
    IReadOnlyList<AmendmentDeltaRequest> Deltas);

public sealed record CreateSalesReservationRequest(
    Guid SalesOrderPublicId,
    long SalesOrderVersion,
    Guid SalesOrderLinePublicId,
    Guid WarehousePublicId,
    decimal Quantity);

public sealed record ChangeSalesReservationRequest(decimal Quantity);

public sealed record CreateDispatchLineRequest(
    int Sequence,
    Guid SalesOrderLinePublicId,
    decimal Quantity,
    Guid? LocationPublicId,
    Guid? LotPublicId,
    Guid? SerialPublicId,
    Guid? ReservationPublicId);

public sealed record CreateDispatchRequest(
    string Number,
    Guid SalesOrderPublicId,
    long SalesOrderVersion,
    Guid WarehousePublicId,
    IReadOnlyList<CreateDispatchLineRequest> Lines);

public sealed record ReverseDispatchRequest(
    string ReversalNumber,
    string Reason);

public sealed record CreateInvoiceDraftLineRequest(
    int Sequence,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal Quantity,
    decimal UnitPrice,
    decimal LineDiscountPercent,
    decimal TaxPercent,
    Guid? SourceDocumentPublicId,
    Guid? SourceLinePublicId,
    long? SourceVersion);

public sealed record CreateInvoiceDraftRequest(
    string Number,
    Guid CustomerPartyPublicId,
    SalesInvoiceSourceMode SourceMode,
    DateOnly DocumentDate,
    DateOnly DueDate,
    string CurrencyCode,
    decimal DocumentDiscountPercent,
    IReadOnlyList<CreateInvoiceDraftLineRequest> Lines);

public sealed record ReplaceInvoiceDraftRequest(
    long Version,
    string Number,
    Guid CustomerPartyPublicId,
    SalesInvoiceSourceMode SourceMode,
    DateOnly DocumentDate,
    DateOnly DueDate,
    string CurrencyCode,
    decimal DocumentDiscountPercent,
    IReadOnlyList<CreateInvoiceDraftLineRequest> Lines);
