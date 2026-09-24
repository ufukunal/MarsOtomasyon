namespace Mars.Domain.Products;

public enum ProductKind { Goods = 1, Service = 2 }
public enum ProductState { Active = 1, Inactive = 2 }
public enum ProductMasterRecordState { Active = 1, Inactive = 2 }
public enum ProductTrackingStrategy { None = 1, Lot = 2, Serial = 3, LotSerial = 4 }
public enum ProductUomRole { Base = 1, Alternate = 2 }

public sealed class Product
{
    private Product(
        Guid publicId, Guid companyId, string productCode, string name, string? description,
        ProductKind kind, bool sellable, bool purchasable, bool stockable,
        ProductTrackingStrategy trackingStrategy, DateTimeOffset createdAt)
    {
        PublicId = publicId;
        CompanyId = companyId;
        ProductCode = productCode;
        Name = name;
        Description = description;
        Kind = kind;
        Sellable = sellable;
        Purchasable = purchasable;
        Stockable = stockable;
        TrackingStrategy = trackingStrategy;
        State = ProductState.Active;
        Version = 1;
        CreatedAt = createdAt;
    }

    public Guid PublicId { get; }
    public Guid CompanyId { get; }
    public string ProductCode { get; }
    public string Name { get; }
    public string? Description { get; }
    public ProductKind Kind { get; }
    public bool Sellable { get; }
    public bool Purchasable { get; }
    public bool Stockable { get; }
    public ProductTrackingStrategy TrackingStrategy { get; }
    public ProductState State { get; }
    public long Version { get; }
    public DateTimeOffset CreatedAt { get; }

    public static Product Create(
        Guid publicId, Guid companyId, string productCode, string name, string? description,
        ProductKind kind, bool sellable, bool purchasable, bool stockable,
        ProductTrackingStrategy trackingStrategy, DateTimeOffset createdAt)
    {
        RequiredId(publicId, nameof(publicId));
        RequiredId(companyId, nameof(companyId));
        if (!Enum.IsDefined(kind)) throw new ArgumentOutOfRangeException(nameof(kind));
        if (!Enum.IsDefined(trackingStrategy)) throw new ArgumentOutOfRangeException(nameof(trackingStrategy));
        if (kind == ProductKind.Service && stockable)
            throw new ArgumentException("SERVICE Product cannot be STOCKABLE.", nameof(stockable));
        if (!stockable && trackingStrategy != ProductTrackingStrategy.None)
            throw new ArgumentException("Non-STOCKABLE Product must use NONE tracking.", nameof(trackingStrategy));

        return new Product(
            publicId, companyId,
            RequiredText(productCode, nameof(productCode)),
            RequiredText(name, nameof(name)),
            OptionalText(description), kind, sellable, purchasable, stockable, trackingStrategy, createdAt);
    }

    private static void RequiredId(Guid value, string name)
    {
        if (value == Guid.Empty) throw new ArgumentException("Identifier is required.", name);
    }

    internal static string RequiredText(string value, string name)
    {
        if (string.IsNullOrWhiteSpace(value)) throw new ArgumentException("Value is required.", name);
        if (!string.Equals(value, value.Trim(), StringComparison.Ordinal))
            throw new ArgumentException("Value cannot contain leading or trailing whitespace.", name);
        return value;
    }

    internal static string? OptionalText(string? value)
    {
        if (string.IsNullOrWhiteSpace(value)) return null;
        if (!string.Equals(value, value.Trim(), StringComparison.Ordinal))
            throw new ArgumentException("Value cannot contain leading or trailing whitespace.");
        return value;
    }
}

public sealed record UnitOfMeasure(
    Guid PublicId, Guid CompanyId, string Code, string Name,
    ProductMasterRecordState State, long Version, DateTimeOffset CreatedAt)
{
    public static UnitOfMeasure Create(Guid publicId, Guid companyId, string code, string name, DateTimeOffset createdAt)
    {
        if (publicId == Guid.Empty) throw new ArgumentException("Public id is required.", nameof(publicId));
        if (companyId == Guid.Empty) throw new ArgumentException("Company id is required.", nameof(companyId));
        return new UnitOfMeasure(
            publicId, companyId, Product.RequiredText(code, nameof(code)),
            Product.RequiredText(name, nameof(name)), ProductMasterRecordState.Active, 1, createdAt);
    }
}

public sealed record ProductVariant(
    Guid PublicId, Guid ProductPublicId, Guid CompanyId, string? VariantCode, string Name,
    ProductTrackingStrategy? TrackingStrategy, ProductMasterRecordState State, long Version, DateTimeOffset CreatedAt)
{
    public static ProductVariant Create(
        Guid publicId, Guid productPublicId, Guid companyId, string? variantCode, string name,
        ProductTrackingStrategy? trackingStrategy, DateTimeOffset createdAt)
    {
        if (publicId == Guid.Empty) throw new ArgumentException("Public id is required.", nameof(publicId));
        if (productPublicId == Guid.Empty) throw new ArgumentException("Product id is required.", nameof(productPublicId));
        if (companyId == Guid.Empty) throw new ArgumentException("Company id is required.", nameof(companyId));
        if (trackingStrategy.HasValue && !Enum.IsDefined(trackingStrategy.Value))
            throw new ArgumentOutOfRangeException(nameof(trackingStrategy));

        return new ProductVariant(
            publicId, productPublicId, companyId, Product.OptionalText(variantCode),
            Product.RequiredText(name, nameof(name)), trackingStrategy,
            ProductMasterRecordState.Active, 1, createdAt);
    }
}

public sealed record ProductUomAssignment(
    Guid PublicId, Guid ProductPublicId, Guid? VariantPublicId, Guid UomPublicId, Guid CompanyId,
    ProductUomRole Role, decimal ConversionFactor, ProductMasterRecordState State, long Version, DateTimeOffset CreatedAt)
{
    public static ProductUomAssignment Create(
        Guid publicId, Guid productPublicId, Guid? variantPublicId, Guid uomPublicId, Guid companyId,
        ProductUomRole role, decimal conversionFactor, DateTimeOffset createdAt)
    {
        if (publicId == Guid.Empty) throw new ArgumentException("Public id is required.", nameof(publicId));
        if (productPublicId == Guid.Empty) throw new ArgumentException("Product id is required.", nameof(productPublicId));
        if (variantPublicId == Guid.Empty) throw new ArgumentException("Variant id cannot be empty.", nameof(variantPublicId));
        if (uomPublicId == Guid.Empty) throw new ArgumentException("UOM id is required.", nameof(uomPublicId));
        if (companyId == Guid.Empty) throw new ArgumentException("Company id is required.", nameof(companyId));
        if (!Enum.IsDefined(role)) throw new ArgumentOutOfRangeException(nameof(role));
        if (conversionFactor <= 0m) throw new ArgumentOutOfRangeException(nameof(conversionFactor));
        if (role == ProductUomRole.Base && variantPublicId.HasValue)
            throw new ArgumentException("Base UOM is Product-level.", nameof(variantPublicId));
        if (role == ProductUomRole.Base && conversionFactor != 1m)
            throw new ArgumentException("Base UOM conversion factor must be one.", nameof(conversionFactor));

        return new ProductUomAssignment(
            publicId, productPublicId, variantPublicId, uomPublicId, companyId, role, conversionFactor,
            ProductMasterRecordState.Active, 1, createdAt);
    }
}

public sealed record ProductBarcodeMapping(
    Guid PublicId, Guid ProductPublicId, Guid? VariantPublicId, Guid? ProductUomPublicId, Guid CompanyId,
    string Namespace, string Value, ProductMasterRecordState State, long Version, DateTimeOffset CreatedAt)
{
    public static ProductBarcodeMapping Create(
        Guid publicId, Guid productPublicId, Guid? variantPublicId, Guid? productUomPublicId,
        Guid companyId, string barcodeNamespace, string value, DateTimeOffset createdAt)
    {
        if (publicId == Guid.Empty) throw new ArgumentException("Public id is required.", nameof(publicId));
        if (productPublicId == Guid.Empty) throw new ArgumentException("Product id is required.", nameof(productPublicId));
        if (variantPublicId == Guid.Empty) throw new ArgumentException("Variant id cannot be empty.", nameof(variantPublicId));
        if (productUomPublicId == Guid.Empty) throw new ArgumentException("Product UOM id cannot be empty.", nameof(productUomPublicId));
        if (companyId == Guid.Empty) throw new ArgumentException("Company id is required.", nameof(companyId));

        return new ProductBarcodeMapping(
            publicId, productPublicId, variantPublicId, productUomPublicId, companyId,
            Product.RequiredText(barcodeNamespace, nameof(barcodeNamespace)).ToUpperInvariant(),
            Product.RequiredText(value, nameof(value)), ProductMasterRecordState.Active, 1, createdAt);
    }
}

public sealed record ProductCategory(
    Guid PublicId, Guid CompanyId, string Code, string Name, Guid? ParentCategoryPublicId,
    long Version, DateTimeOffset CreatedAt)
{
    public static ProductCategory Create(
        Guid publicId, Guid companyId, string code, string name, Guid? parentCategoryPublicId, DateTimeOffset createdAt)
    {
        if (publicId == Guid.Empty) throw new ArgumentException("Public id is required.", nameof(publicId));
        if (companyId == Guid.Empty) throw new ArgumentException("Company id is required.", nameof(companyId));
        if (parentCategoryPublicId == Guid.Empty)
            throw new ArgumentException("Parent Category id cannot be empty.", nameof(parentCategoryPublicId));
        if (parentCategoryPublicId == publicId)
            throw new ArgumentException("Category cannot be its own parent.", nameof(parentCategoryPublicId));

        return new ProductCategory(
            publicId, companyId, Product.RequiredText(code, nameof(code)),
            Product.RequiredText(name, nameof(name)), parentCategoryPublicId, 1, createdAt);
    }
}

public sealed record ProductExternalMapping(
    Guid PublicId, Guid ProductPublicId, Guid? VariantPublicId, Guid CompanyId,
    string SystemCode, string AccountScope, string ExternalIdentity,
    ProductMasterRecordState State, long Version, DateTimeOffset CreatedAt)
{
    public static ProductExternalMapping Create(
        Guid publicId, Guid productPublicId, Guid? variantPublicId, Guid companyId,
        string systemCode, string? accountScope, string externalIdentity, DateTimeOffset createdAt)
    {
        if (publicId == Guid.Empty) throw new ArgumentException("Public id is required.", nameof(publicId));
        if (productPublicId == Guid.Empty) throw new ArgumentException("Product id is required.", nameof(productPublicId));
        if (variantPublicId == Guid.Empty) throw new ArgumentException("Variant id cannot be empty.", nameof(variantPublicId));
        if (companyId == Guid.Empty) throw new ArgumentException("Company id is required.", nameof(companyId));

        return new ProductExternalMapping(
            publicId, productPublicId, variantPublicId, companyId,
            Product.RequiredText(systemCode, nameof(systemCode)).ToUpperInvariant(),
            string.IsNullOrWhiteSpace(accountScope) ? string.Empty : accountScope.Trim(),
            Product.RequiredText(externalIdentity, nameof(externalIdentity)),
            ProductMasterRecordState.Active, 1, createdAt);
    }
}
