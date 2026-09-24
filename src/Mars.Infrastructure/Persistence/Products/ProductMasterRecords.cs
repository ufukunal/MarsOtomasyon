using Mars.Domain.Products;

namespace Mars.Infrastructure.Persistence.Products;

internal sealed class ProductRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public string ProductCode { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public string? Description { get; set; }
    public ProductKind Kind { get; set; }
    public bool Sellable { get; set; }
    public bool Purchasable { get; set; }
    public bool Stockable { get; set; }
    public ProductTrackingStrategy TrackingStrategy { get; set; }
    public ProductState State { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class UnitOfMeasureRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public string Code { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public ProductMasterRecordState State { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class ProductVariantRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long ProductId { get; set; }
    public Guid CompanyId { get; set; }
    public string? VariantCode { get; set; }
    public string Name { get; set; } = string.Empty;
    public ProductTrackingStrategy? TrackingStrategy { get; set; }
    public ProductMasterRecordState State { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class ProductUomRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public long UomId { get; set; }
    public Guid CompanyId { get; set; }
    public ProductUomRole Role { get; set; }
    public decimal ConversionFactor { get; set; }
    public ProductMasterRecordState State { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class ProductBarcodeRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public long? ProductUomId { get; set; }
    public Guid CompanyId { get; set; }
    public string Namespace { get; set; } = string.Empty;
    public string Value { get; set; } = string.Empty;
    public ProductMasterRecordState State { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class ProductCategoryRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public string Code { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public long? ParentCategoryId { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class ProductCategoryLinkRecord
{
    public long Id { get; set; }
    public long ProductId { get; set; }
    public long CategoryId { get; set; }
    public Guid CompanyId { get; set; }
    public bool IsPrimary { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class ProductExternalMappingRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public Guid CompanyId { get; set; }
    public string SystemCode { get; set; } = string.Empty;
    public string AccountScope { get; set; } = string.Empty;
    public string ExternalIdentity { get; set; } = string.Empty;
    public ProductMasterRecordState State { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}
