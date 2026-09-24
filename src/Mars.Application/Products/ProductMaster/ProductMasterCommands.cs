using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Results;
using Mars.Domain.Products;

namespace Mars.Application.Products.ProductMaster;

public enum ProductMasterMutationOutcome
{
    Succeeded = 1,
    ProductNotFound = 2,
    ChildNotFound = 3,
    DuplicateOperation = 4,
    StaleVersion = 5,
    StateConflict = 6,
    DeterministicConflict = 7,
    BusinessConflict = 8
}

public sealed record ProductMasterMutationPersistenceResult(
    ProductMasterMutationOutcome Outcome, Guid? EntityPublicId = null, string? State = null, long? Version = null);

public sealed record ProductMasterMutationReceipt(
    Guid EntityPublicId, string EntityType, string State, long Version, string CorrelationId);

public sealed record ProductMasterWriteContext(
    Guid CompanyId, IdempotencyOperation Idempotency, AuditEntry Audit);

public sealed record CreateUomWrite(UnitOfMeasure Uom, ProductMasterWriteContext Context);
public sealed record ChangeUomStateWrite(Guid UomPublicId, long ExpectedVersion, ProductMasterRecordState TargetState, ProductMasterWriteContext Context);
public sealed record CreateProductWrite(Product Product, Guid BaseUomPublicId, ProductMasterWriteContext Context);
public sealed record EditProductWrite(Guid ProductPublicId, long ExpectedVersion, string ProductCode, string Name, string? Description, bool Sellable, bool Purchasable, ProductMasterWriteContext Context);
public sealed record ChangeProductStateWrite(Guid ProductPublicId, long ExpectedVersion, ProductState TargetState, ProductMasterWriteContext Context);
public sealed record CreateVariantWrite(ProductVariant Variant, ProductMasterWriteContext Context);
public sealed record EditVariantWrite(Guid ProductPublicId, Guid VariantPublicId, long ExpectedVersion, string? VariantCode, string Name, ProductTrackingStrategy? TrackingStrategy, ProductMasterWriteContext Context);
public sealed record ChangeVariantStateWrite(Guid ProductPublicId, Guid VariantPublicId, long ExpectedVersion, ProductMasterRecordState TargetState, ProductMasterWriteContext Context);
public sealed record AddProductUomWrite(ProductUomAssignment Assignment, ProductMasterWriteContext Context);
public sealed record UpdateProductUomWrite(Guid ProductPublicId, Guid ProductUomPublicId, long ExpectedVersion, decimal ConversionFactor, ProductMasterWriteContext Context);
public sealed record ChangeProductUomStateWrite(Guid ProductPublicId, Guid ProductUomPublicId, long ExpectedVersion, ProductMasterRecordState TargetState, ProductMasterWriteContext Context);
public sealed record CreateBarcodeWrite(ProductBarcodeMapping Barcode, ProductMasterWriteContext Context);
public sealed record ChangeBarcodeStateWrite(Guid ProductPublicId, Guid BarcodePublicId, long ExpectedVersion, ProductMasterRecordState TargetState, ProductMasterWriteContext Context);
public sealed record CreateCategoryWrite(ProductCategory Category, ProductMasterWriteContext Context);
public sealed record EditCategoryWrite(Guid CategoryPublicId, long ExpectedVersion, string Code, string Name, Guid? ParentCategoryPublicId, ProductMasterWriteContext Context);
public sealed record AssignCategoryWrite(Guid ProductPublicId, Guid CategoryPublicId, bool IsPrimary, ProductMasterWriteContext Context);
public sealed record UnassignCategoryWrite(Guid ProductPublicId, Guid CategoryPublicId, ProductMasterWriteContext Context);
public sealed record CreateProductExternalMappingWrite(ProductExternalMapping Mapping, ProductMasterWriteContext Context);
public sealed record ChangeProductExternalMappingStateWrite(Guid ProductPublicId, Guid MappingPublicId, long ExpectedVersion, ProductMasterRecordState TargetState, ProductMasterWriteContext Context);

public interface IProductMasterMutationPersistence
{
    Task<ProductMasterMutationPersistenceResult> CreateUomAsync(CreateUomWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> ChangeUomStateAsync(ChangeUomStateWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> CreateProductAsync(CreateProductWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> EditProductAsync(EditProductWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> ChangeProductStateAsync(ChangeProductStateWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> CreateVariantAsync(CreateVariantWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> EditVariantAsync(EditVariantWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> ChangeVariantStateAsync(ChangeVariantStateWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> AddProductUomAsync(AddProductUomWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> UpdateProductUomAsync(UpdateProductUomWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> ChangeProductUomStateAsync(ChangeProductUomStateWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> CreateBarcodeAsync(CreateBarcodeWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> ChangeBarcodeStateAsync(ChangeBarcodeStateWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> CreateCategoryAsync(CreateCategoryWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> EditCategoryAsync(EditCategoryWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> AssignCategoryAsync(AssignCategoryWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> UnassignCategoryAsync(UnassignCategoryWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> CreateExternalMappingAsync(CreateProductExternalMappingWrite write, CancellationToken cancellationToken);
    Task<ProductMasterMutationPersistenceResult> ChangeExternalMappingStateAsync(ChangeProductExternalMappingStateWrite write, CancellationToken cancellationToken);
}

public sealed record CreateUomCommand(string Code, string Name, string OperationKey);
public sealed record ChangeUomStateCommand(Guid UomPublicId, long ExpectedVersion, string State, string OperationKey);
public sealed record CreateProductCommand(string ProductCode, string Name, string? Description, string Kind, bool Sellable, bool Purchasable, bool Stockable, string TrackingStrategy, Guid BaseUomPublicId, string OperationKey);
public sealed record EditProductCommand(Guid ProductPublicId, long ExpectedVersion, string ProductCode, string Name, string? Description, bool Sellable, bool Purchasable, string OperationKey);
public sealed record ChangeProductStateCommand(Guid ProductPublicId, long ExpectedVersion, string State, string OperationKey);
public sealed record CreateVariantCommand(Guid ProductPublicId, string? VariantCode, string Name, string? TrackingStrategy, string OperationKey);
public sealed record EditVariantCommand(Guid ProductPublicId, Guid VariantPublicId, long ExpectedVersion, string? VariantCode, string Name, string? TrackingStrategy, string OperationKey);
public sealed record ChangeVariantStateCommand(Guid ProductPublicId, Guid VariantPublicId, long ExpectedVersion, string State, string OperationKey);
public sealed record AddProductUomCommand(Guid ProductPublicId, Guid? VariantPublicId, Guid UomPublicId, decimal ConversionFactor, string OperationKey);
public sealed record UpdateProductUomCommand(Guid ProductPublicId, Guid ProductUomPublicId, long ExpectedVersion, decimal ConversionFactor, string OperationKey);
public sealed record ChangeProductUomStateCommand(Guid ProductPublicId, Guid ProductUomPublicId, long ExpectedVersion, string State, string OperationKey);
public sealed record CreateBarcodeCommand(Guid ProductPublicId, Guid? VariantPublicId, Guid? ProductUomPublicId, string Namespace, string Value, string OperationKey);
public sealed record ChangeBarcodeStateCommand(Guid ProductPublicId, Guid BarcodePublicId, long ExpectedVersion, string State, string OperationKey);
public sealed record CreateCategoryCommand(string Code, string Name, Guid? ParentCategoryPublicId, string OperationKey);
public sealed record EditCategoryCommand(Guid CategoryPublicId, long ExpectedVersion, string Code, string Name, Guid? ParentCategoryPublicId, string OperationKey);
public sealed record AssignCategoryCommand(Guid ProductPublicId, Guid CategoryPublicId, bool IsPrimary, string OperationKey);
public sealed record UnassignCategoryCommand(Guid ProductPublicId, Guid CategoryPublicId, string OperationKey);
public sealed record CreateProductExternalMappingCommand(Guid ProductPublicId, Guid? VariantPublicId, string SystemCode, string? AccountScope, string ExternalIdentity, string OperationKey);
public sealed record ChangeProductExternalMappingStateCommand(Guid ProductPublicId, Guid MappingPublicId, long ExpectedVersion, string State, string OperationKey);

public sealed class ProductMasterCommandHandler(
    IPermissionEvaluator permissionEvaluator,
    IProductMasterMutationPersistence persistence)
{
    private const int MaxOperationKeyLength = 128;

    public async Task<Result<ProductMasterMutationReceipt>> CreateUomAsync(
        CreateUomCommand command, IExecutionContext context, CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(ProductPermissions.UomManage, context, cancellationToken)) return Denied("manage Product UOMs");
        var code = Required(command.Code);
        var name = Required(command.Name);
        if (code is null || name is null)
            return Validation("products.uom.invalid", "UOM code and name are required and must be trimmed.");
        var publicId = Guid.NewGuid();
        var writeContext = CreateContext("products.uom.create", command.OperationKey, "ProductUomCreated", "UnitOfMeasure", publicId, context);
        if (writeContext.Error is not null) return Result<ProductMasterMutationReceipt>.Failure(writeContext.Error);
        var uom = UnitOfMeasure.Create(publicId, context.CompanyId, code, name, writeContext.Context!.Audit.OccurredAt);
        return Map(await persistence.CreateUomAsync(new CreateUomWrite(uom, writeContext.Context), cancellationToken), "UnitOfMeasure", context, "products.uom");
    }

    public Task<Result<ProductMasterMutationReceipt>> ChangeUomStateAsync(
        ChangeUomStateCommand command, IExecutionContext context, CancellationToken cancellationToken) =>
        ChangeChildStateAsync(
            ProductPermissions.UomManage, "manage Product UOMs", "products.uom.state", "UnitOfMeasure",
            command.UomPublicId, command.ExpectedVersion, command.State, command.OperationKey, context, cancellationToken,
            (state, writeContext) => persistence.ChangeUomStateAsync(
                new ChangeUomStateWrite(command.UomPublicId, command.ExpectedVersion, state, writeContext), cancellationToken));

    public async Task<Result<ProductMasterMutationReceipt>> CreateProductAsync(
        CreateProductCommand command, IExecutionContext context, CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(ProductPermissions.Create, context, cancellationToken)) return Denied("create Products");
        var code = Required(command.ProductCode);
        var name = Required(command.Name);
        var description = Optional(command.Description, out var descValid);
        var kind = ParseKind(command.Kind);
        var tracking = ParseTracking(command.TrackingStrategy);
        if (code is null || name is null || !descValid || kind is null || tracking is null || command.BaseUomPublicId == Guid.Empty)
            return Validation("products.create.invalid", "Product code, name, kind, tracking strategy and Base UOM are required and text must be trimmed.");
        if (kind == ProductKind.Service && command.Stockable)
            return Validation("products.create.service_stockable", "SERVICE Product cannot be STOCKABLE.");
        if (!command.Stockable && tracking != ProductTrackingStrategy.None)
            return Validation("products.create.tracking_invalid", "Non-STOCKABLE Product must use NONE tracking.");

        var publicId = Guid.NewGuid();
        var writeContext = CreateContext("products.create", command.OperationKey, "ProductCreated", "Product", publicId, context);
        if (writeContext.Error is not null) return Result<ProductMasterMutationReceipt>.Failure(writeContext.Error);
        var product = Product.Create(
            publicId, context.CompanyId, code, name, description, kind.Value,
            command.Sellable, command.Purchasable, command.Stockable, tracking.Value,
            writeContext.Context!.Audit.OccurredAt);
        return Map(
            await persistence.CreateProductAsync(new CreateProductWrite(product, command.BaseUomPublicId, writeContext.Context), cancellationToken),
            "Product", context, "products.create");
    }

    public async Task<Result<ProductMasterMutationReceipt>> EditProductAsync(
        EditProductCommand command, IExecutionContext context, CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(ProductPermissions.Edit, context, cancellationToken)) return Denied("edit Products");
        if (command.ProductPublicId == Guid.Empty || command.ExpectedVersion <= 0)
            return Validation("products.edit.identity_invalid", "Product id and positive expected version are required.");
        var code = Required(command.ProductCode);
        var name = Required(command.Name);
        var description = Optional(command.Description, out var descValid);
        if (code is null || name is null || !descValid)
            return Validation("products.edit.invalid", "Product code/name must be trimmed and description cannot contain surrounding whitespace.");
        var writeContext = CreateContext("products.edit", command.OperationKey, "ProductChanged", "Product", command.ProductPublicId, context);
        if (writeContext.Error is not null) return Result<ProductMasterMutationReceipt>.Failure(writeContext.Error);
        return Map(
            await persistence.EditProductAsync(
                new EditProductWrite(command.ProductPublicId, command.ExpectedVersion, code, name, description, command.Sellable, command.Purchasable, writeContext.Context!),
                cancellationToken),
            "Product", context, "products.edit");
    }

    public async Task<Result<ProductMasterMutationReceipt>> ChangeProductStateAsync(
        ChangeProductStateCommand command, IExecutionContext context, CancellationToken cancellationToken)
    {
        var state = ParseProductState(command.State);
        if (state is null) return Validation("products.state.invalid", "Product state must be ACTIVE or INACTIVE.");
        var permission = state == ProductState.Active ? ProductPermissions.Reactivate : ProductPermissions.Deactivate;
        if (!await AuthorizedAsync(permission, context, cancellationToken))
            return Denied(state == ProductState.Active ? "reactivate Products" : "deactivate Products");
        if (command.ProductPublicId == Guid.Empty || command.ExpectedVersion <= 0)
            return Validation("products.state.identity_invalid", "Product id and positive expected version are required.");
        var writeContext = CreateContext(
            "products.state", command.OperationKey,
            state == ProductState.Active ? "ProductActivated" : "ProductDeactivated",
            "Product", command.ProductPublicId, context);
        if (writeContext.Error is not null) return Result<ProductMasterMutationReceipt>.Failure(writeContext.Error);
        return Map(
            await persistence.ChangeProductStateAsync(
                new ChangeProductStateWrite(command.ProductPublicId, command.ExpectedVersion, state.Value, writeContext.Context!),
                cancellationToken),
            "Product", context, "products.state");
    }

    public async Task<Result<ProductMasterMutationReceipt>> CreateVariantAsync(
        CreateVariantCommand command, IExecutionContext context, CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(ProductPermissions.VariantManage, context, cancellationToken)) return Denied("manage Product Variants");
        if (command.ProductPublicId == Guid.Empty) return Validation("products.variant.product_required", "Product id is required.");
        var code = Optional(command.VariantCode, out var codeValid);
        var name = Required(command.Name);
        var tracking = ParseOptionalTracking(command.TrackingStrategy, out var trackingValid);
        if (!codeValid || name is null || !trackingValid)
            return Validation("products.variant.invalid", "Variant code/name/tracking must be valid and trimmed.");
        var publicId = Guid.NewGuid();
        var writeContext = CreateContext("products.variant.create", command.OperationKey, "ProductVariantCreated", "ProductVariant", publicId, context);
        if (writeContext.Error is not null) return Result<ProductMasterMutationReceipt>.Failure(writeContext.Error);
        var variant = ProductVariant.Create(publicId, command.ProductPublicId, context.CompanyId, code, name, tracking, writeContext.Context!.Audit.OccurredAt);
        return Map(
            await persistence.CreateVariantAsync(new CreateVariantWrite(variant, writeContext.Context), cancellationToken),
            "ProductVariant", context, "products.variant");
    }

    public async Task<Result<ProductMasterMutationReceipt>> EditVariantAsync(
        EditVariantCommand command, IExecutionContext context, CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(ProductPermissions.VariantManage, context, cancellationToken)) return Denied("manage Product Variants");
        if (command.ProductPublicId == Guid.Empty || command.VariantPublicId == Guid.Empty || command.ExpectedVersion <= 0)
            return Validation("products.variant.identity_invalid", "Product/Variant ids and positive expected version are required.");
        var code = Optional(command.VariantCode, out var codeValid);
        var name = Required(command.Name);
        var tracking = ParseOptionalTracking(command.TrackingStrategy, out var trackingValid);
        if (!codeValid || name is null || !trackingValid)
            return Validation("products.variant.invalid", "Variant code/name/tracking must be valid and trimmed.");
        var writeContext = CreateContext("products.variant.edit", command.OperationKey, "ProductVariantChanged", "ProductVariant", command.VariantPublicId, context);
        if (writeContext.Error is not null) return Result<ProductMasterMutationReceipt>.Failure(writeContext.Error);
        return Map(
            await persistence.EditVariantAsync(
                new EditVariantWrite(command.ProductPublicId, command.VariantPublicId, command.ExpectedVersion, code, name, tracking, writeContext.Context!),
                cancellationToken),
            "ProductVariant", context, "products.variant");
    }

    public Task<Result<ProductMasterMutationReceipt>> ChangeVariantStateAsync(
        ChangeVariantStateCommand command, IExecutionContext context, CancellationToken cancellationToken) =>
        ChangeChildStateAsync(
            ProductPermissions.VariantManage, "manage Product Variants", "products.variant.state", "ProductVariant",
            command.VariantPublicId, command.ExpectedVersion, command.State, command.OperationKey, context, cancellationToken,
            (state, writeContext) => persistence.ChangeVariantStateAsync(
                new ChangeVariantStateWrite(command.ProductPublicId, command.VariantPublicId, command.ExpectedVersion, state, writeContext),
                cancellationToken));

    public async Task<Result<ProductMasterMutationReceipt>> AddProductUomAsync(
        AddProductUomCommand command, IExecutionContext context, CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(ProductPermissions.UomManage, context, cancellationToken)) return Denied("manage Product UOMs");
        if (command.ProductPublicId == Guid.Empty || command.UomPublicId == Guid.Empty ||
            command.VariantPublicId == Guid.Empty || command.ConversionFactor <= 0m)
            return Validation("products.product_uom.invalid", "Product/UOM ids and positive conversion factor are required.");
        var publicId = Guid.NewGuid();
        var writeContext = CreateContext("products.product_uom.create", command.OperationKey, "ProductUomCreated", "ProductUom", publicId, context);
        if (writeContext.Error is not null) return Result<ProductMasterMutationReceipt>.Failure(writeContext.Error);
        var assignment = ProductUomAssignment.Create(
            publicId, command.ProductPublicId, command.VariantPublicId, command.UomPublicId,
            context.CompanyId, ProductUomRole.Alternate, command.ConversionFactor, writeContext.Context!.Audit.OccurredAt);
        return Map(
            await persistence.AddProductUomAsync(new AddProductUomWrite(assignment, writeContext.Context), cancellationToken),
            "ProductUom", context, "products.product_uom");
    }

    public async Task<Result<ProductMasterMutationReceipt>> UpdateProductUomAsync(
        UpdateProductUomCommand command, IExecutionContext context, CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(ProductPermissions.UomManage, context, cancellationToken)) return Denied("manage Product UOMs");
        if (command.ProductPublicId == Guid.Empty || command.ProductUomPublicId == Guid.Empty ||
            command.ExpectedVersion <= 0 || command.ConversionFactor <= 0m)
            return Validation("products.product_uom.invalid", "Product/Product-UOM ids, positive expected version and positive conversion factor are required.");
        var writeContext = CreateContext("products.product_uom.edit", command.OperationKey, "ProductUomChanged", "ProductUom", command.ProductUomPublicId, context);
        if (writeContext.Error is not null) return Result<ProductMasterMutationReceipt>.Failure(writeContext.Error);
        return Map(
            await persistence.UpdateProductUomAsync(
                new UpdateProductUomWrite(command.ProductPublicId, command.ProductUomPublicId, command.ExpectedVersion, command.ConversionFactor, writeContext.Context!),
                cancellationToken),
            "ProductUom", context, "products.product_uom");
    }

    public Task<Result<ProductMasterMutationReceipt>> ChangeProductUomStateAsync(
        ChangeProductUomStateCommand command, IExecutionContext context, CancellationToken cancellationToken) =>
        ChangeChildStateAsync(
            ProductPermissions.UomManage, "manage Product UOMs", "products.product_uom.state", "ProductUom",
            command.ProductUomPublicId, command.ExpectedVersion, command.State, command.OperationKey, context, cancellationToken,
            (state, writeContext) => persistence.ChangeProductUomStateAsync(
                new ChangeProductUomStateWrite(command.ProductPublicId, command.ProductUomPublicId, command.ExpectedVersion, state, writeContext),
                cancellationToken));

    public async Task<Result<ProductMasterMutationReceipt>> CreateBarcodeAsync(
        CreateBarcodeCommand command, IExecutionContext context, CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(ProductPermissions.BarcodeManage, context, cancellationToken)) return Denied("manage Product barcodes");
        if (command.ProductPublicId == Guid.Empty || command.VariantPublicId == Guid.Empty || command.ProductUomPublicId == Guid.Empty)
            return Validation("products.barcode.identity_invalid", "Product id is required and optional Variant/Product-UOM ids cannot be empty.");
        var barcodeNamespace = Required(command.Namespace);
        var value = Required(command.Value);
        if (barcodeNamespace is null || value is null)
            return Validation("products.barcode.invalid", "Barcode namespace and value are required and must be trimmed.");
        var publicId = Guid.NewGuid();
        var writeContext = CreateContext("products.barcode.create", command.OperationKey, "ProductBarcodeCreated", "ProductBarcode", publicId, context);
        if (writeContext.Error is not null) return Result<ProductMasterMutationReceipt>.Failure(writeContext.Error);
        var barcode = ProductBarcodeMapping.Create(
            publicId, command.ProductPublicId, command.VariantPublicId, command.ProductUomPublicId,
            context.CompanyId, barcodeNamespace, value, writeContext.Context!.Audit.OccurredAt);
        return Map(
            await persistence.CreateBarcodeAsync(new CreateBarcodeWrite(barcode, writeContext.Context), cancellationToken),
            "ProductBarcode", context, "products.barcode");
    }

    public Task<Result<ProductMasterMutationReceipt>> ChangeBarcodeStateAsync(
        ChangeBarcodeStateCommand command, IExecutionContext context, CancellationToken cancellationToken) =>
        ChangeChildStateAsync(
            ProductPermissions.BarcodeManage, "manage Product barcodes", "products.barcode.state", "ProductBarcode",
            command.BarcodePublicId, command.ExpectedVersion, command.State, command.OperationKey, context, cancellationToken,
            (state, writeContext) => persistence.ChangeBarcodeStateAsync(
                new ChangeBarcodeStateWrite(command.ProductPublicId, command.BarcodePublicId, command.ExpectedVersion, state, writeContext),
                cancellationToken));

    public async Task<Result<ProductMasterMutationReceipt>> CreateCategoryAsync(
        CreateCategoryCommand command, IExecutionContext context, CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(ProductPermissions.CategoryManage, context, cancellationToken)) return Denied("manage Product categories");
        if (command.ParentCategoryPublicId == Guid.Empty)
            return Validation("products.category.parent_invalid", "Parent Category id cannot be empty.");
        var code = Required(command.Code);
        var name = Required(command.Name);
        if (code is null || name is null)
            return Validation("products.category.invalid", "Category code and name are required and must be trimmed.");
        var publicId = Guid.NewGuid();
        var writeContext = CreateContext("products.category.create", command.OperationKey, "ProductCategoryCreated", "ProductCategory", publicId, context);
        if (writeContext.Error is not null) return Result<ProductMasterMutationReceipt>.Failure(writeContext.Error);
        var category = ProductCategory.Create(publicId, context.CompanyId, code, name, command.ParentCategoryPublicId, writeContext.Context!.Audit.OccurredAt);
        return Map(
            await persistence.CreateCategoryAsync(new CreateCategoryWrite(category, writeContext.Context), cancellationToken),
            "ProductCategory", context, "products.category");
    }

    public async Task<Result<ProductMasterMutationReceipt>> EditCategoryAsync(
        EditCategoryCommand command, IExecutionContext context, CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(ProductPermissions.CategoryManage, context, cancellationToken)) return Denied("manage Product categories");
        if (command.CategoryPublicId == Guid.Empty || command.ExpectedVersion <= 0 || command.ParentCategoryPublicId == Guid.Empty)
            return Validation("products.category.identity_invalid", "Category id and positive expected version are required; parent id cannot be empty.");
        var code = Required(command.Code);
        var name = Required(command.Name);
        if (code is null || name is null)
            return Validation("products.category.invalid", "Category code and name are required and must be trimmed.");
        var writeContext = CreateContext("products.category.edit", command.OperationKey, "ProductCategoryChanged", "ProductCategory", command.CategoryPublicId, context);
        if (writeContext.Error is not null) return Result<ProductMasterMutationReceipt>.Failure(writeContext.Error);
        return Map(
            await persistence.EditCategoryAsync(
                new EditCategoryWrite(command.CategoryPublicId, command.ExpectedVersion, code, name, command.ParentCategoryPublicId, writeContext.Context!),
                cancellationToken),
            "ProductCategory", context, "products.category");
    }

    public async Task<Result<ProductMasterMutationReceipt>> AssignCategoryAsync(
        AssignCategoryCommand command, IExecutionContext context, CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(ProductPermissions.CategoryManage, context, cancellationToken)) return Denied("manage Product categories");
        if (command.ProductPublicId == Guid.Empty || command.CategoryPublicId == Guid.Empty)
            return Validation("products.category.assignment_invalid", "Product and Category ids are required.");
        var writeContext = CreateContext("products.category.assign", command.OperationKey, "ProductCategoryAssigned", "Product", command.ProductPublicId, context);
        if (writeContext.Error is not null) return Result<ProductMasterMutationReceipt>.Failure(writeContext.Error);
        return Map(
            await persistence.AssignCategoryAsync(
                new AssignCategoryWrite(command.ProductPublicId, command.CategoryPublicId, command.IsPrimary, writeContext.Context!),
                cancellationToken),
            "Product", context, "products.category");
    }

    public async Task<Result<ProductMasterMutationReceipt>> UnassignCategoryAsync(
        UnassignCategoryCommand command, IExecutionContext context, CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(ProductPermissions.CategoryManage, context, cancellationToken)) return Denied("manage Product categories");
        if (command.ProductPublicId == Guid.Empty || command.CategoryPublicId == Guid.Empty)
            return Validation("products.category.assignment_invalid", "Product and Category ids are required.");
        var writeContext = CreateContext("products.category.unassign", command.OperationKey, "ProductCategoryUnassigned", "Product", command.ProductPublicId, context);
        if (writeContext.Error is not null) return Result<ProductMasterMutationReceipt>.Failure(writeContext.Error);
        return Map(
            await persistence.UnassignCategoryAsync(
                new UnassignCategoryWrite(command.ProductPublicId, command.CategoryPublicId, writeContext.Context!),
                cancellationToken),
            "Product", context, "products.category");
    }

    public async Task<Result<ProductMasterMutationReceipt>> CreateExternalMappingAsync(
        CreateProductExternalMappingCommand command, IExecutionContext context, CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(ProductPermissions.Edit, context, cancellationToken)) return Denied("edit Product external mappings");
        if (command.ProductPublicId == Guid.Empty || command.VariantPublicId == Guid.Empty)
            return Validation("products.external_mapping.identity_invalid", "Product id is required and optional Variant id cannot be empty.");
        var system = Required(command.SystemCode);
        var external = Required(command.ExternalIdentity);
        var account = Optional(command.AccountScope, out var accountValid);
        if (system is null || external is null || !accountValid)
            return Validation("products.external_mapping.invalid", "System/external identity are required and values must be trimmed.");
        var publicId = Guid.NewGuid();
        var writeContext = CreateContext("products.external_mapping.create", command.OperationKey, "ProductExternalMappingCreated", "ProductExternalMapping", publicId, context);
        if (writeContext.Error is not null) return Result<ProductMasterMutationReceipt>.Failure(writeContext.Error);
        var mapping = ProductExternalMapping.Create(
            publicId, command.ProductPublicId, command.VariantPublicId, context.CompanyId,
            system, account, external, writeContext.Context!.Audit.OccurredAt);
        return Map(
            await persistence.CreateExternalMappingAsync(new CreateProductExternalMappingWrite(mapping, writeContext.Context), cancellationToken),
            "ProductExternalMapping", context, "products.external_mapping");
    }

    public Task<Result<ProductMasterMutationReceipt>> ChangeExternalMappingStateAsync(
        ChangeProductExternalMappingStateCommand command, IExecutionContext context, CancellationToken cancellationToken) =>
        ChangeChildStateAsync(
            ProductPermissions.Edit, "edit Product external mappings", "products.external_mapping.state", "ProductExternalMapping",
            command.MappingPublicId, command.ExpectedVersion, command.State, command.OperationKey, context, cancellationToken,
            (state, writeContext) => persistence.ChangeExternalMappingStateAsync(
                new ChangeProductExternalMappingStateWrite(command.ProductPublicId, command.MappingPublicId, command.ExpectedVersion, state, writeContext),
                cancellationToken));

    private async Task<Result<ProductMasterMutationReceipt>> ChangeChildStateAsync(
        string permission, string action, string scope, string entityType,
        Guid entityPublicId, long expectedVersion, string stateText, string operationKey,
        IExecutionContext context, CancellationToken cancellationToken,
        Func<ProductMasterRecordState, ProductMasterWriteContext, Task<ProductMasterMutationPersistenceResult>> persist)
    {
        if (!await AuthorizedAsync(permission, context, cancellationToken)) return Denied(action);
        if (entityPublicId == Guid.Empty || expectedVersion <= 0)
            return Validation(scope + ".identity_invalid", "Entity id and positive expected version are required.");
        var state = ParseMasterState(stateText);
        if (state is null) return Validation(scope + ".invalid", "State must be ACTIVE or INACTIVE.");
        var writeContext = CreateContext(
            scope, operationKey,
            state == ProductMasterRecordState.Active ? entityType + "Activated" : entityType + "Deactivated",
            entityType, entityPublicId, context);
        if (writeContext.Error is not null) return Result<ProductMasterMutationReceipt>.Failure(writeContext.Error);
        return Map(await persist(state.Value, writeContext.Context!), entityType, context, scope);
    }

    private Task<bool> AuthorizedAsync(string permission, IExecutionContext context, CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(context);
        return permissionEvaluator.IsGrantedAsync(context.ActorId, context.CompanyId, permission, cancellationToken);
    }

    private static Result<ProductMasterMutationReceipt> Map(
        ProductMasterMutationPersistenceResult result, string entityType, IExecutionContext context, string codePrefix)
    {
        if (result.Outcome != ProductMasterMutationOutcome.Succeeded)
            return Result<ProductMasterMutationReceipt>.Failure(MapError(result.Outcome, codePrefix));
        return Result<ProductMasterMutationReceipt>.Success(new ProductMasterMutationReceipt(
            result.EntityPublicId ?? throw new InvalidOperationException("Mutation did not return entity public id."),
            entityType,
            result.State ?? "ACTIVE",
            result.Version ?? throw new InvalidOperationException("Mutation did not return entity version."),
            context.CorrelationId.Value));
    }

    private static ApplicationError MapError(ProductMasterMutationOutcome outcome, string codePrefix) => outcome switch
    {
        ProductMasterMutationOutcome.ProductNotFound => new(ErrorCategory.NotFound, "products.product_not_found", "Product was not found in the current company."),
        ProductMasterMutationOutcome.ChildNotFound => new(ErrorCategory.NotFound, codePrefix + ".not_found", "The requested Product master record was not found in the current company."),
        ProductMasterMutationOutcome.DuplicateOperation => new(ErrorCategory.Conflict, codePrefix + ".duplicate_operation", "The Idempotency-Key has already been used for this Product operation in the current company."),
        ProductMasterMutationOutcome.StaleVersion => new(ErrorCategory.Concurrency, codePrefix + ".stale_version", "The Product master record changed after the supplied version was read."),
        ProductMasterMutationOutcome.StateConflict => new(ErrorCategory.Conflict, codePrefix + ".state_conflict", "The Product master record is already in, or cannot enter, the requested state."),
        ProductMasterMutationOutcome.DeterministicConflict => new(ErrorCategory.Conflict, codePrefix + ".deterministic_conflict", "A deterministic Product master uniqueness conflict must be resolved."),
        ProductMasterMutationOutcome.BusinessConflict => new(ErrorCategory.BusinessRule, codePrefix + ".business_conflict", "The requested Product master change violates a frozen Product invariant."),
        _ => new(ErrorCategory.Infrastructure, codePrefix + ".unexpected", "The Product operation returned an unexpected persistence result.")
    };

    private static (ProductMasterWriteContext? Context, ApplicationError? Error) CreateContext(
        string scope, string operationKey, string action, string entityType,
        Guid entityPublicId, IExecutionContext context)
    {
        var key = operationKey ?? string.Empty;
        if (string.IsNullOrWhiteSpace(key))
            return (null, new ApplicationError(ErrorCategory.Validation, scope + ".idempotency_key_required", "Idempotency-Key is required."));
        if (!string.Equals(key, key.Trim(), StringComparison.Ordinal) || key.Length > MaxOperationKeyLength)
            return (null, new ApplicationError(ErrorCategory.Validation, scope + ".idempotency_key_invalid",
                $"Idempotency-Key must be trimmed and cannot exceed {MaxOperationKeyLength} characters."));

        var now = DateTimeOffset.UtcNow;
        return (
            new ProductMasterWriteContext(
                context.CompanyId,
                new IdempotencyOperation(scope + ":" + context.CompanyId.ToString("D"), key, null, now),
                new AuditEntry(
                    context.ActorId, context.CompanyId, context.BranchId, context.CorrelationId.Value,
                    "Products", action, entityType, entityPublicId, null, now)),
            null);
    }

    private static Result<ProductMasterMutationReceipt> Validation(string code, string message) =>
        Result<ProductMasterMutationReceipt>.Failure(new ApplicationError(ErrorCategory.Validation, code, message));

    private static Result<ProductMasterMutationReceipt> Denied(string action) =>
        Result<ProductMasterMutationReceipt>.Failure(
            new ApplicationError(ErrorCategory.Authorization, "authorization.permission_denied",
                $"The current actor is not authorized to {action}."));

    private static string? Required(string? value) =>
        string.IsNullOrWhiteSpace(value) || !string.Equals(value, value.Trim(), StringComparison.Ordinal) ? null : value;

    private static string? Optional(string? value, out bool valid)
    {
        if (string.IsNullOrWhiteSpace(value)) { valid = true; return null; }
        valid = string.Equals(value, value.Trim(), StringComparison.Ordinal);
        return valid ? value : null;
    }

    private static ProductKind? ParseKind(string value) => value switch
    {
        "GOODS" => ProductKind.Goods,
        "SERVICE" => ProductKind.Service,
        _ => null
    };

    private static ProductTrackingStrategy? ParseTracking(string value) => value switch
    {
        "NONE" => ProductTrackingStrategy.None,
        "LOT" => ProductTrackingStrategy.Lot,
        "SERIAL" => ProductTrackingStrategy.Serial,
        "LOT_SERIAL" => ProductTrackingStrategy.LotSerial,
        _ => null
    };

    private static ProductTrackingStrategy? ParseOptionalTracking(string? value, out bool valid)
    {
        if (string.IsNullOrWhiteSpace(value)) { valid = true; return null; }
        var parsed = ParseTracking(value);
        valid = parsed.HasValue;
        return parsed;
    }

    private static ProductState? ParseProductState(string value) => value switch
    {
        "ACTIVE" => ProductState.Active,
        "INACTIVE" => ProductState.Inactive,
        _ => null
    };

    private static ProductMasterRecordState? ParseMasterState(string value) => value switch
    {
        "ACTIVE" => ProductMasterRecordState.Active,
        "INACTIVE" => ProductMasterRecordState.Inactive,
        _ => null
    };
}
