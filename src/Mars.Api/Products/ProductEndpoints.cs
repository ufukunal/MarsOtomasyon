using Mars.Api.Foundation.Errors;
using Mars.Application.Foundation.Context;
using Mars.Application.Products;
using Mars.Application.Products.ProductMaster;

namespace Mars.Api.Products;

public static class ProductEndpoints
{
    public static void MapProductEndpoints(this WebApplication app)
    {
        app.MapGet(
                "/api/v1/products",
                async (string? search, IExecutionContext context, ProductMasterQueryHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.ListProductsAsync(search, context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Ok(result.Value);
                })
            .RequireAuthorization(ProductPermissions.Read)
            .WithName("ListProducts");

        app.MapGet(
                "/api/v1/products/uoms",
                async (IExecutionContext context, ProductMasterQueryHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.ListUomsAsync(context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Ok(result.Value);
                })
            .RequireAuthorization(ProductPermissions.UomRead)
            .WithName("ListProductUoms");

        app.MapPost(
                "/api/v1/products/uoms",
                async (CreateUomRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.CreateUomAsync(
                        new CreateUomCommand(request.Code, request.Name, Key(http)), context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Created("/api/v1/products/uoms/" + result.Value!.EntityPublicId.ToString("D"), result.Value);
                })
            .RequireAuthorization(ProductPermissions.UomManage)
            .WithName("CreateProductUomMaster");

        app.MapPost(
                "/api/v1/products/uoms/{uomPublicId:guid}/state",
                async (Guid uomPublicId, ChangeProductRecordStateRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.ChangeUomStateAsync(
                        new ChangeUomStateCommand(uomPublicId, request.Version, request.State, Key(http)), context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Ok(result.Value);
                })
            .RequireAuthorization(ProductPermissions.UomManage)
            .WithName("ChangeProductUomMasterState");

        app.MapGet(
                "/api/v1/products/categories",
                async (IExecutionContext context, ProductMasterQueryHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.ListCategoriesAsync(context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Ok(result.Value);
                })
            .RequireAuthorization(ProductPermissions.CategoryRead)
            .WithName("ListProductCategories");

        app.MapPost(
                "/api/v1/products/categories",
                async (CreateProductCategoryRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.CreateCategoryAsync(
                        new CreateCategoryCommand(request.Code, request.Name, request.ParentCategoryPublicId, Key(http)), context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Created("/api/v1/products/categories/" + result.Value!.EntityPublicId.ToString("D"), result.Value);
                })
            .RequireAuthorization(ProductPermissions.CategoryManage)
            .WithName("CreateProductCategory");

        app.MapPut(
                "/api/v1/products/categories/{categoryPublicId:guid}",
                async (Guid categoryPublicId, EditProductCategoryRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.EditCategoryAsync(
                        new EditCategoryCommand(
                            categoryPublicId, request.Version, request.Code, request.Name,
                            request.ParentCategoryPublicId, Key(http)),
                        context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Ok(result.Value);
                })
            .RequireAuthorization(ProductPermissions.CategoryManage)
            .WithName("EditProductCategory");

        app.MapPost(
                "/api/v1/products",
                async (CreateProductRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.CreateProductAsync(
                        new CreateProductCommand(
                            request.ProductCode, request.Name, request.Description, request.Kind,
                            request.Sellable, request.Purchasable, request.Stockable,
                            request.TrackingStrategy, request.BaseUomPublicId, Key(http)),
                        context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Created("/api/v1/products/" + result.Value!.EntityPublicId.ToString("D"), result.Value);
                })
            .RequireAuthorization(ProductPermissions.Create)
            .WithName("CreateProduct");

        app.MapGet(
                "/api/v1/products/{productPublicId:guid}",
                async (Guid productPublicId, IExecutionContext context, ProductMasterQueryHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.GetProductAsync(productPublicId, context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Ok(result.Value);
                })
            .RequireAuthorization(ProductPermissions.Read)
            .WithName("GetProduct");

        app.MapPut(
                "/api/v1/products/{productPublicId:guid}",
                async (Guid productPublicId, EditProductRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.EditProductAsync(
                        new EditProductCommand(
                            productPublicId, request.Version, request.ProductCode, request.Name,
                            request.Description, request.Sellable, request.Purchasable, Key(http)),
                        context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Ok(result.Value);
                })
            .RequireAuthorization(ProductPermissions.Edit)
            .WithName("EditProduct");

        app.MapPost(
                "/api/v1/products/{productPublicId:guid}/deactivate",
                async (Guid productPublicId, ProductStateRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.ChangeProductStateAsync(
                        new ChangeProductStateCommand(productPublicId, request.Version, "INACTIVE", Key(http)), context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Ok(result.Value);
                })
            .RequireAuthorization(ProductPermissions.Deactivate)
            .WithName("DeactivateProduct");

        app.MapPost(
                "/api/v1/products/{productPublicId:guid}/reactivate",
                async (Guid productPublicId, ProductStateRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.ChangeProductStateAsync(
                        new ChangeProductStateCommand(productPublicId, request.Version, "ACTIVE", Key(http)), context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Ok(result.Value);
                })
            .RequireAuthorization(ProductPermissions.Reactivate)
            .WithName("ReactivateProduct");

        app.MapPost(
                "/api/v1/products/{productPublicId:guid}/variants",
                async (Guid productPublicId, CreateProductVariantRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.CreateVariantAsync(
                        new CreateVariantCommand(
                            productPublicId, request.VariantCode, request.Name,
                            request.TrackingStrategy, Key(http)),
                        context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Created(
                            "/api/v1/products/" + productPublicId.ToString("D") +
                            "/variants/" + result.Value!.EntityPublicId.ToString("D"),
                            result.Value);
                })
            .RequireAuthorization(ProductPermissions.VariantManage)
            .WithName("CreateProductVariant");

        app.MapPut(
                "/api/v1/products/{productPublicId:guid}/variants/{variantPublicId:guid}",
                async (Guid productPublicId, Guid variantPublicId, EditProductVariantRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.EditVariantAsync(
                        new EditVariantCommand(
                            productPublicId, variantPublicId, request.Version,
                            request.VariantCode, request.Name, request.TrackingStrategy, Key(http)),
                        context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Ok(result.Value);
                })
            .RequireAuthorization(ProductPermissions.VariantManage)
            .WithName("EditProductVariant");

        app.MapPost(
                "/api/v1/products/{productPublicId:guid}/variants/{variantPublicId:guid}/state",
                async (Guid productPublicId, Guid variantPublicId, ChangeProductRecordStateRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.ChangeVariantStateAsync(
                        new ChangeVariantStateCommand(
                            productPublicId, variantPublicId, request.Version, request.State, Key(http)),
                        context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Ok(result.Value);
                })
            .RequireAuthorization(ProductPermissions.VariantManage)
            .WithName("ChangeProductVariantState");

        app.MapPost(
                "/api/v1/products/{productPublicId:guid}/uoms",
                async (Guid productPublicId, AddProductUomRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.AddProductUomAsync(
                        new AddProductUomCommand(
                            productPublicId, request.VariantPublicId, request.UomPublicId,
                            request.ConversionFactor, Key(http)),
                        context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Created(
                            "/api/v1/products/" + productPublicId.ToString("D") +
                            "/uoms/" + result.Value!.EntityPublicId.ToString("D"),
                            result.Value);
                })
            .RequireAuthorization(ProductPermissions.UomManage)
            .WithName("AddProductUom");

        app.MapPut(
                "/api/v1/products/{productPublicId:guid}/uoms/{productUomPublicId:guid}",
                async (Guid productPublicId, Guid productUomPublicId, UpdateProductUomRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.UpdateProductUomAsync(
                        new UpdateProductUomCommand(
                            productPublicId, productUomPublicId, request.Version,
                            request.ConversionFactor, Key(http)),
                        context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Ok(result.Value);
                })
            .RequireAuthorization(ProductPermissions.UomManage)
            .WithName("UpdateProductUom");

        app.MapPost(
                "/api/v1/products/{productPublicId:guid}/uoms/{productUomPublicId:guid}/state",
                async (Guid productPublicId, Guid productUomPublicId, ChangeProductRecordStateRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.ChangeProductUomStateAsync(
                        new ChangeProductUomStateCommand(
                            productPublicId, productUomPublicId, request.Version, request.State, Key(http)),
                        context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Ok(result.Value);
                })
            .RequireAuthorization(ProductPermissions.UomManage)
            .WithName("ChangeProductUomState");

        app.MapPost(
                "/api/v1/products/{productPublicId:guid}/barcodes",
                async (Guid productPublicId, CreateProductBarcodeRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.CreateBarcodeAsync(
                        new CreateBarcodeCommand(
                            productPublicId, request.VariantPublicId, request.ProductUomPublicId,
                            request.Namespace, request.Value, Key(http)),
                        context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Created(
                            "/api/v1/products/" + productPublicId.ToString("D") +
                            "/barcodes/" + result.Value!.EntityPublicId.ToString("D"),
                            result.Value);
                })
            .RequireAuthorization(ProductPermissions.BarcodeManage)
            .WithName("CreateProductBarcode");

        app.MapPost(
                "/api/v1/products/{productPublicId:guid}/barcodes/{barcodePublicId:guid}/state",
                async (Guid productPublicId, Guid barcodePublicId, ChangeProductRecordStateRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.ChangeBarcodeStateAsync(
                        new ChangeBarcodeStateCommand(
                            productPublicId, barcodePublicId, request.Version, request.State, Key(http)),
                        context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Ok(result.Value);
                })
            .RequireAuthorization(ProductPermissions.BarcodeManage)
            .WithName("ChangeProductBarcodeState");

        app.MapPost(
                "/api/v1/products/{productPublicId:guid}/categories",
                async (Guid productPublicId, AssignProductCategoryRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.AssignCategoryAsync(
                        new AssignCategoryCommand(
                            productPublicId, request.CategoryPublicId, request.IsPrimary, Key(http)),
                        context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Ok(result.Value);
                })
            .RequireAuthorization(ProductPermissions.CategoryManage)
            .WithName("AssignProductCategory");

        app.MapPost(
                "/api/v1/products/{productPublicId:guid}/categories/{categoryPublicId:guid}/unassign",
                async (Guid productPublicId, Guid categoryPublicId, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.UnassignCategoryAsync(
                        new UnassignCategoryCommand(productPublicId, categoryPublicId, Key(http)), context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Ok(result.Value);
                })
            .RequireAuthorization(ProductPermissions.CategoryManage)
            .WithName("UnassignProductCategory");

        app.MapPost(
                "/api/v1/products/{productPublicId:guid}/external-mappings",
                async (Guid productPublicId, CreateProductExternalMappingRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.CreateExternalMappingAsync(
                        new CreateProductExternalMappingCommand(
                            productPublicId, request.VariantPublicId, request.SystemCode,
                            request.AccountScope, request.ExternalIdentity, Key(http)),
                        context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Created(
                            "/api/v1/products/" + productPublicId.ToString("D") +
                            "/external-mappings/" + result.Value!.EntityPublicId.ToString("D"),
                            result.Value);
                })
            .RequireAuthorization(ProductPermissions.Edit)
            .WithName("CreateProductExternalMapping");

        app.MapPost(
                "/api/v1/products/{productPublicId:guid}/external-mappings/{mappingPublicId:guid}/state",
                async (Guid productPublicId, Guid mappingPublicId, ChangeProductRecordStateRequest request, HttpRequest http, IExecutionContext context, ProductMasterCommandHandler handler, CancellationToken ct) =>
                {
                    var result = await handler.ChangeExternalMappingStateAsync(
                        new ChangeProductExternalMappingStateCommand(
                            productPublicId, mappingPublicId, request.Version, request.State, Key(http)),
                        context, ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Ok(result.Value);
                })
            .RequireAuthorization(ProductPermissions.Edit)
            .WithName("ChangeProductExternalMappingState");
    }

    private static string Key(HttpRequest request) =>
        request.Headers["Idempotency-Key"].ToString();
}
