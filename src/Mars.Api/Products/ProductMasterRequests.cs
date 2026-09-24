namespace Mars.Api.Products;

public sealed record CreateUomRequest(string Code, string Name);
public sealed record ChangeProductRecordStateRequest(long Version, string State);
public sealed record CreateProductRequest(
    string ProductCode, string Name, string? Description, string Kind,
    bool Sellable, bool Purchasable, bool Stockable, string TrackingStrategy,
    Guid BaseUomPublicId);
public sealed record EditProductRequest(
    long Version, string ProductCode, string Name, string? Description,
    bool Sellable, bool Purchasable);
public sealed record ProductStateRequest(long Version);
public sealed record CreateProductVariantRequest(string? VariantCode, string Name, string? TrackingStrategy);
public sealed record EditProductVariantRequest(long Version, string? VariantCode, string Name, string? TrackingStrategy);
public sealed record AddProductUomRequest(Guid? VariantPublicId, Guid UomPublicId, decimal ConversionFactor);
public sealed record UpdateProductUomRequest(long Version, decimal ConversionFactor);
public sealed record CreateProductBarcodeRequest(
    Guid? VariantPublicId, Guid? ProductUomPublicId, string Namespace, string Value);
public sealed record CreateProductCategoryRequest(string Code, string Name, Guid? ParentCategoryPublicId);
public sealed record EditProductCategoryRequest(
    long Version, string Code, string Name, Guid? ParentCategoryPublicId);
public sealed record AssignProductCategoryRequest(Guid CategoryPublicId, bool IsPrimary);
public sealed record CreateProductExternalMappingRequest(
    Guid? VariantPublicId, string SystemCode, string? AccountScope, string ExternalIdentity);
