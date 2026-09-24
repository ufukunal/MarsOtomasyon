using Mars.Domain.Sales;

namespace Mars.Infrastructure.Persistence.Sales;

internal sealed class SalesProformaRecord
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
    public decimal DocumentDiscountPercent { get; set; }
    public SalesProformaSourceMode SourceMode { get; set; }
    public Guid SourceDocumentPublicId { get; set; }
    public long SourceVersion { get; set; }
    public SalesProformaState State { get; set; }
    public long Version { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? CancelledAt { get; set; }
}

internal sealed class SalesProformaLineRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long SalesProformaId { get; set; }
    public Guid CompanyId { get; set; }
    public int Sequence { get; set; }
    public Guid SourceLinePublicId { get; set; }
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
