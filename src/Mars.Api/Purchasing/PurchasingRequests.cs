using Mars.Domain.Purchasing;

namespace Mars.Api.Purchasing;

public sealed record PurchasingTradeLineRequest(
    int Sequence,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal Quantity,
    decimal UnitPrice,
    decimal LineDiscountPercent,
    decimal TaxPercent);

public sealed record CreatePurchaseOrderRequest(
    string Number,
    Guid SupplierPartyPublicId,
    string CurrencyCode,
    string? PaymentTerms,
    decimal DocumentDiscountPercent,
    IReadOnlyList<PurchasingTradeLineRequest> Lines);

public sealed record PurchasingVersionRequest(long Version);

public sealed record CreateGoodsReceiptLineRequest(
    int Sequence,
    Guid PurchaseOrderLinePublicId,
    decimal Quantity,
    Guid? LocationPublicId,
    Guid? LotPublicId,
    Guid? SerialPublicId);

public sealed record CreateGoodsReceiptRequest(
    string Number,
    Guid PurchaseOrderPublicId,
    long PurchaseOrderVersion,
    Guid WarehousePublicId,
    IReadOnlyList<CreateGoodsReceiptLineRequest> Lines);

public sealed record CreateSupplierInvoiceDraftLineRequest(
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

public sealed record CreateSupplierInvoiceDraftRequest(
    string Number,
    Guid SupplierPartyPublicId,
    SupplierInvoiceSourceMode SourceMode,
    DateOnly DocumentDate,
    DateOnly DueDate,
    string CurrencyCode,
    decimal DocumentDiscountPercent,
    IReadOnlyList<CreateSupplierInvoiceDraftLineRequest> Lines);
