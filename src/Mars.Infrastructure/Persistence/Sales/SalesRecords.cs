using Mars.Domain.Sales;

namespace Mars.Infrastructure.Persistence.Sales;

internal sealed class QuoteRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public string Number { get; set; } = string.Empty;
    public long CustomerPartyId { get; set; }
    public string CustomerCodeSnapshot { get; set; } = string.Empty;
    public string CustomerNameSnapshot { get; set; } = string.Empty;
    public string CurrencyCode { get; set; } = string.Empty;
    public string? PaymentTerms { get; set; }
    public QuoteState State { get; set; }
    public long CurrentRevisionNumber { get; set; }
    public long Version { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? AcceptedAt { get; set; }
    public DateTimeOffset? ExpiredAt { get; set; }
    public DateTimeOffset? CancelledAt { get; set; }
}

internal sealed class QuoteRevisionRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long QuoteId { get; set; }
    public Guid CompanyId { get; set; }
    public long RevisionNumber { get; set; }
    public decimal DocumentDiscountPercent { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? SubmittedForApprovalAt { get; set; }
    public DateTimeOffset? SentToCustomerAt { get; set; }
    public DateTimeOffset? AcceptedAt { get; set; }
}

internal sealed class QuoteLineRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long RevisionId { get; set; }
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

internal sealed class QuoteConversionLinkRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public long QuoteRevisionId { get; set; }
    public Guid QuoteLinePublicId { get; set; }
    public long SalesOrderId { get; set; }
    public long SalesOrderVersionNumber { get; set; }
    public Guid SalesOrderLinePublicId { get; set; }
    public decimal Quantity { get; set; }
    public Guid ActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class SalesOrderRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public string Number { get; set; } = string.Empty;
    public long CustomerPartyId { get; set; }
    public string CustomerCodeSnapshot { get; set; } = string.Empty;
    public string CustomerNameSnapshot { get; set; } = string.Empty;
    public string CurrencyCode { get; set; } = string.Empty;
    public string? PaymentTerms { get; set; }
    public SalesOrderState State { get; set; }
    public long CurrentVersionNumber { get; set; }
    public bool ApprovalInheritedFromAcceptedQuote { get; set; }
    public Guid CreatorActorId { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? ConfirmedAt { get; set; }
    public DateTimeOffset? ClosedAt { get; set; }
}

internal sealed class SalesOrderVersionRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long SalesOrderId { get; set; }
    public Guid CompanyId { get; set; }
    public long VersionNumber { get; set; }
    public long? SourceQuoteRevisionId { get; set; }
    public long? SourceAmendmentId { get; set; }
    public decimal DocumentDiscountPercent { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class SalesOrderLineRecord
{
    public long Id { get; set; }
    public Guid LinePublicId { get; set; }
    public long SalesOrderVersionId { get; set; }
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

internal sealed class SalesOrderAmendmentRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long SalesOrderId { get; set; }
    public Guid CompanyId { get; set; }
    public long BaseVersionNumber { get; set; }
    public SalesOrderAmendmentState State { get; set; }
    public bool RequiresApproval { get; set; }
    public string? NewPaymentTerms { get; set; }
    public string Reason { get; set; } = string.Empty;
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? ActivatedAt { get; set; }
}

internal sealed class SalesOrderAmendmentDeltaRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long AmendmentId { get; set; }
    public Guid CompanyId { get; set; }
    public Guid SalesOrderLinePublicId { get; set; }
    public decimal QuantityDelta { get; set; }
    public decimal? NewUnitPrice { get; set; }
    public decimal? NewLineDiscountPercent { get; set; }
    public decimal? NewTaxPercent { get; set; }
}

internal sealed class DispatchRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public string Number { get; set; } = string.Empty;
    public long SalesOrderId { get; set; }
    public long SalesOrderVersionNumber { get; set; }
    public long WarehouseId { get; set; }
    public DispatchState State { get; set; }
    public long Version { get; set; }
    public Guid CreatorActorId { get; set; }
    public long? ReversalOfDispatchId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? ReadyAt { get; set; }
    public DateTimeOffset? PostedAt { get; set; }
    public DateTimeOffset? HandedOverAt { get; set; }
    public DateTimeOffset? DeliveredAt { get; set; }
    public DateTimeOffset? CancelledAt { get; set; }
}

internal sealed class DispatchLineRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long DispatchId { get; set; }
    public Guid CompanyId { get; set; }
    public int Sequence { get; set; }
    public Guid SalesOrderLinePublicId { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public long UomId { get; set; }
    public decimal ConversionFactorSnapshot { get; set; }
    public decimal Quantity { get; set; }
    public long WarehouseId { get; set; }
    public long? LocationId { get; set; }
    public long? LotId { get; set; }
    public long? SerialId { get; set; }
    public Guid? ReservationPublicId { get; set; }
}

internal sealed class DispatchSourceAllocationRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long DispatchLineId { get; set; }
    public Guid CompanyId { get; set; }
    public long WarehouseId { get; set; }
    public long LocationId { get; set; }
    public long? LotId { get; set; }
    public long? SerialId { get; set; }
    public decimal Quantity { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class DispatchInventoryEffectLinkRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long DispatchLineId { get; set; }
    public Guid CompanyId { get; set; }
    public Guid InventoryMovementPublicId { get; set; }
    public Guid? OriginalInventoryMovementPublicId { get; set; }
    public bool IsReversal { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class SalesInvoiceRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public string Number { get; set; } = string.Empty;
    public long CustomerPartyId { get; set; }
    public SalesInvoiceState State { get; set; }
    public SalesInvoiceSourceMode SourceMode { get; set; }
    public DateOnly DocumentDate { get; set; }
    public DateOnly DueDate { get; set; }
    public string CurrencyCode { get; set; } = string.Empty;
    public decimal DocumentDiscountPercent { get; set; }
    public string CustomerCodeSnapshot { get; set; } = string.Empty;
    public string CustomerLegalNameSnapshot { get; set; } = string.Empty;
    public string? CustomerTaxSchemeSnapshot { get; set; }
    public string? CustomerTaxValueSnapshot { get; set; }
    public string? BillingAddressSnapshot { get; set; }
    public string? ShippingAddressSnapshot { get; set; }
    public decimal NetTotal { get; set; }
    public decimal TaxTotal { get; set; }
    public decimal GrossTotal { get; set; }
    public long Version { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? CancelledAt { get; set; }
}

internal sealed class SalesInvoiceLineRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long SalesInvoiceId { get; set; }
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

internal sealed class SalesInvoiceSourceLinkRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long SalesInvoiceLineId { get; set; }
    public Guid CompanyId { get; set; }
    public SalesInvoiceSourceMode SourceMode { get; set; }
    public Guid? SourceDocumentPublicId { get; set; }
    public Guid? SourceLinePublicId { get; set; }
    public long? SourceVersion { get; set; }
    public decimal Quantity { get; set; }
}
