using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;

namespace Mars.Application.Products.ProductMaster;

public sealed record ProductListItem(
    Guid PublicId, string ProductCode, string Name, string Kind,
    bool Sellable, bool Purchasable, bool Stockable, string TrackingStrategy,
    string BaseUomCode, string? PrimaryCategory, string State, long Version);

public sealed record ProductVariantView(
    Guid PublicId, string? VariantCode, string Name, string? TrackingStrategy, string State, long Version);

public sealed record ProductUomView(
    Guid PublicId, Guid UomPublicId, string UomCode, string UomName, Guid? VariantPublicId,
    string Role, decimal ConversionFactor, string State, long Version);

public sealed record ProductBarcodeView(
    Guid PublicId, string Namespace, string Value, Guid? VariantPublicId,
    Guid? ProductUomPublicId, string State, long Version);

public sealed record ProductCategoryView(
    Guid PublicId, string Code, string Name, Guid? ParentCategoryPublicId, bool IsPrimary, long Version);

public sealed record ProductExternalMappingView(
    Guid PublicId, string SystemCode, string? AccountScope, string ExternalIdentity,
    Guid? VariantPublicId, string State, long Version);

public sealed record ProductDetailView(
    Guid PublicId, string ProductCode, string Name, string? Description, string Kind,
    bool Sellable, bool Purchasable, bool Stockable, string TrackingStrategy,
    string State, long Version,
    IReadOnlyList<ProductVariantView> Variants,
    IReadOnlyList<ProductUomView> Uoms,
    IReadOnlyList<ProductBarcodeView> Barcodes,
    IReadOnlyList<ProductCategoryView> Categories,
    IReadOnlyList<ProductExternalMappingView> ExternalMappings);

public sealed record UnitOfMeasureView(Guid PublicId, string Code, string Name, string State, long Version);
public sealed record CategoryLookupView(Guid PublicId, string Code, string Name, Guid? ParentCategoryPublicId, long Version);
public sealed record ProductDetailReadOptions(bool IncludeVariants, bool IncludeUoms, bool IncludeBarcodes, bool IncludeCategories);

public interface IProductMasterReadPersistence
{
    Task<IReadOnlyList<ProductListItem>> ListProductsAsync(Guid companyId, string? search, CancellationToken cancellationToken);
    Task<ProductDetailView?> GetProductAsync(Guid companyId, Guid productPublicId, ProductDetailReadOptions options, CancellationToken cancellationToken);
    Task<IReadOnlyList<UnitOfMeasureView>> ListUomsAsync(Guid companyId, CancellationToken cancellationToken);
    Task<IReadOnlyList<CategoryLookupView>> ListCategoriesAsync(Guid companyId, CancellationToken cancellationToken);
}

public sealed class ProductMasterQueryHandler(
    IPermissionEvaluator permissionEvaluator,
    IProductMasterReadPersistence persistence)
{
    public async Task<Result<IReadOnlyList<ProductListItem>>> ListProductsAsync(
        string? search, IExecutionContext executionContext, CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(executionContext);
        if (!await GrantedAsync(ProductPermissions.Read, executionContext, cancellationToken))
            return Result<IReadOnlyList<ProductListItem>>.Failure(Denied("read Products"));

        return Result<IReadOnlyList<ProductListItem>>.Success(
            await persistence.ListProductsAsync(
                executionContext.CompanyId,
                string.IsNullOrWhiteSpace(search) ? null : search.Trim(),
                cancellationToken));
    }

    public async Task<Result<ProductDetailView>> GetProductAsync(
        Guid productPublicId, IExecutionContext executionContext, CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(executionContext);
        if (productPublicId == Guid.Empty)
            return Result<ProductDetailView>.Failure(new ApplicationError(
                ErrorCategory.Validation, "products.read.product_required", "Product public id is required."));
        if (!await GrantedAsync(ProductPermissions.Read, executionContext, cancellationToken))
            return Result<ProductDetailView>.Failure(Denied("read Products"));

        var detail = await persistence.GetProductAsync(
            executionContext.CompanyId,
            productPublicId,
            new ProductDetailReadOptions(
                await GrantedAsync(ProductPermissions.VariantRead, executionContext, cancellationToken),
                await GrantedAsync(ProductPermissions.UomRead, executionContext, cancellationToken),
                await GrantedAsync(ProductPermissions.BarcodeRead, executionContext, cancellationToken),
                await GrantedAsync(ProductPermissions.CategoryRead, executionContext, cancellationToken)),
            cancellationToken);

        return detail is null
            ? Result<ProductDetailView>.Failure(new ApplicationError(
                ErrorCategory.NotFound, "products.product_not_found", "Product was not found in the current company."))
            : Result<ProductDetailView>.Success(detail);
    }

    public async Task<Result<IReadOnlyList<UnitOfMeasureView>>> ListUomsAsync(
        IExecutionContext executionContext, CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(executionContext);
        if (!await GrantedAsync(ProductPermissions.UomRead, executionContext, cancellationToken))
            return Result<IReadOnlyList<UnitOfMeasureView>>.Failure(Denied("read Product UOMs"));
        return Result<IReadOnlyList<UnitOfMeasureView>>.Success(
            await persistence.ListUomsAsync(executionContext.CompanyId, cancellationToken));
    }

    public async Task<Result<IReadOnlyList<CategoryLookupView>>> ListCategoriesAsync(
        IExecutionContext executionContext, CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(executionContext);
        if (!await GrantedAsync(ProductPermissions.CategoryRead, executionContext, cancellationToken))
            return Result<IReadOnlyList<CategoryLookupView>>.Failure(Denied("read Product categories"));
        return Result<IReadOnlyList<CategoryLookupView>>.Success(
            await persistence.ListCategoriesAsync(executionContext.CompanyId, cancellationToken));
    }

    private Task<bool> GrantedAsync(string permission, IExecutionContext context, CancellationToken cancellationToken) =>
        permissionEvaluator.IsGrantedAsync(context.ActorId, context.CompanyId, permission, cancellationToken);

    private static ApplicationError Denied(string action) =>
        new(ErrorCategory.Authorization, "authorization.permission_denied",
            $"The current actor is not authorized to {action}.");
}
