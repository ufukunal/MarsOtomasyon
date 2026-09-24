using Mars.Domain.Purchasing;

namespace Mars.Infrastructure.Persistence.Purchasing;

internal sealed class PurchaseOrderRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public string Number { get; set; } = string.Empty;
    public long SupplierPartyId { get; set; }
    public string SupplierCodeSnapshot { get; set; } = string.Empty;
    public string SupplierNameSnapshot { get; set; } = string.Empty;
    public string CurrencyCode { get; set; } = string.Empty;
    public string? PaymentTerms { get; set; }
    public PurchaseOrderState State { get; set; }
    public long CurrentVersionNumber { get; set; }
    public long Version { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? ConfirmedAt { get; set; }
    public DateTimeOffset? ClosedAt { get; set; }
}

internal sealed class PurchaseOrderVersionRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long PurchaseOrderId { get; set; }
    public Guid CompanyId { get; set; }
    public long VersionNumber { get; set; }
    public decimal DocumentDiscountPercent { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class PurchaseOrderLineRecord
{
    public long Id { get; set; }
    public Guid LinePublicId { get; set; }
    public long PurchaseOrderVersionId { get; set; }
    public Guid CompanyId { get; set; }
    public int Sequence { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public long UomId { get; set; }
    public decimal ConversionFactorSnapshot { get; set; }
    public string ProductCodeSnapshot { get; set; } = string.Empty;
    public string ProductNameSnapshot { get; set; } = string.Empty;
    public string? VariantCodeSnapshot { get; set; }
    public string? VariantNameSnapshot { get; set; }
    public string UomCodeSnapshot { get; set; } = string.Empty;
    public string UomNameSnapshot { get; set; } = string.Empty;
    public decimal Quantity { get; set; }
    public decimal UnitPrice { get; set; }
    public decimal LineDiscountPercent { get; set; }
    public decimal TaxPercent { get; set; }
}

internal sealed class PurchaseOrderAmendmentRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long PurchaseOrderId { get; set; }
    public Guid CompanyId { get; set; }
    public long BaseVersionNumber { get; set; }
    public long ResultVersionNumber { get; set; }
    public PurchaseOrderAmendmentState State { get; set; }
    public string Reason { get; set; } = string.Empty;
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? ActivatedAt { get; set; }
}

internal sealed class PurchaseOrderAmendmentDeltaRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long AmendmentId { get; set; }
    public Guid CompanyId { get; set; }
    public Guid PurchaseOrderLinePublicId { get; set; }
    public decimal QuantityDelta { get; set; }
}

internal sealed class GoodsReceiptRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public string Number { get; set; } = string.Empty;
    public long PurchaseOrderId { get; set; }
    public long PurchaseOrderVersionNumber { get; set; }
    public long WarehouseId { get; set; }
    public GoodsReceiptState State { get; set; }
    public long Version { get; set; }
    public Guid CreatorActorId { get; set; }
    public long? ReversalOfGoodsReceiptId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? PostedAt { get; set; }
    public DateTimeOffset? CancelledAt { get; set; }
}

internal sealed class GoodsReceiptLineRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long GoodsReceiptId { get; set; }
    public Guid CompanyId { get; set; }
    public int Sequence { get; set; }
    public Guid PurchaseOrderLinePublicId { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public long UomId { get; set; }
    public decimal ConversionFactorSnapshot { get; set; }
    public decimal Quantity { get; set; }
    public long WarehouseId { get; set; }
    public long? LocationId { get; set; }
    public long? LotId { get; set; }
    public long? SerialId { get; set; }
}

internal sealed class GoodsReceiptInventoryEffectLinkRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long GoodsReceiptLineId { get; set; }
    public Guid CompanyId { get; set; }
    public Guid InventoryMovementPublicId { get; set; }
    public Guid? OriginalInventoryMovementPublicId { get; set; }
    public bool IsReversal { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class SupplierInvoiceRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public string Number { get; set; } = string.Empty;
    public long SupplierPartyId { get; set; }
    public SupplierInvoiceState State { get; set; }
    public SupplierInvoiceSourceMode SourceMode { get; set; }
    public DateOnly DocumentDate { get; set; }
    public DateOnly DueDate { get; set; }
    public string CurrencyCode { get; set; } = string.Empty;
    public decimal DocumentDiscountPercent { get; set; }
    public string SupplierCodeSnapshot { get; set; } = string.Empty;
    public string SupplierLegalNameSnapshot { get; set; } = string.Empty;
    public decimal NetTotal { get; set; }
    public decimal TaxTotal { get; set; }
    public decimal GrossTotal { get; set; }
    public long Version { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? CancelledAt { get; set; }
}

internal sealed class SupplierInvoiceLineRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long SupplierInvoiceId { get; set; }
    public Guid CompanyId { get; set; }
    public int Sequence { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public long UomId { get; set; }
    public decimal ConversionFactorSnapshot { get; set; }
    public string ProductCodeSnapshot { get; set; } = string.Empty;
    public string ProductNameSnapshot { get; set; } = string.Empty;
    public string? VariantCodeSnapshot { get; set; }
    public string? VariantNameSnapshot { get; set; }
    public string UomCodeSnapshot { get; set; } = string.Empty;
    public string UomNameSnapshot { get; set; } = string.Empty;
    public decimal Quantity { get; set; }
    public decimal UnitPrice { get; set; }
    public decimal LineDiscountPercent { get; set; }
    public decimal DocumentDiscount { get; set; }
    public decimal TaxPercent { get; set; }
    public decimal TaxableBase { get; set; }
    public decimal TaxAmount { get; set; }
    public decimal LineTotal { get; set; }
}

internal sealed class SupplierInvoiceSourceLinkRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long SupplierInvoiceLineId { get; set; }
    public Guid CompanyId { get; set; }
    public SupplierInvoiceSourceMode SourceMode { get; set; }
    public Guid? SourceDocumentPublicId { get; set; }
    public Guid? SourceLinePublicId { get; set; }
    public long? SourceVersion { get; set; }
    public decimal Quantity { get; set; }
}

internal sealed class PurchaseMatchExceptionRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public long PurchaseMatchResultId { get; set; }
    public string Reason { get; set; } = string.Empty;
    public Guid CreatorActorId { get; set; }
    public Guid? ApprovalDecisionPublicId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? DecidedAt { get; set; }
}

internal sealed class PurchaseMatchResultRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public PurchaseMatchKind Kind { get; set; }
    public PurchaseMatchState State { get; set; }
    public Guid SupplierInvoicePublicId { get; set; }
    public Guid? PurchaseOrderPublicId { get; set; }
    public Guid? GoodsReceiptPublicId { get; set; }
    public decimal QuantityVariance { get; set; }
    public decimal PriceVariance { get; set; }
    public string? BlockReason { get; set; }
    public DateTimeOffset EvaluatedAt { get; set; }
    public Guid EvaluatedByActorId { get; set; }
}
