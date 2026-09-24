using Mars.Api.Foundation.Errors;
using Mars.Application.Foundation.Context;
using Mars.Application.Inventory;
using Mars.Domain.Inventory;

namespace Mars.Api.Inventory;

public static class InventoryEndpoints
{
    public static void MapInventoryEndpoints(this WebApplication app)
    {
        app.MapGet(
                "/api/v1/inventory/warehouses",
                async (
                    string? search,
                    IExecutionContext context,
                    InventoryQueryHandler handler,
                    CancellationToken ct) =>
                {
                    var result = await handler.ListWarehousesAsync(search, context, ct);
                    return Map(result, context);
                })
            .RequireAuthorization(InventoryPermissions.WarehouseRead)
            .WithName("ListInventoryWarehouses");

        app.MapPost(
                "/api/v1/inventory/warehouses",
                async (
                    CreateWarehouseRequest request,
                    HttpRequest http,
                    IExecutionContext context,
                    InventoryMasterCommandHandler handler,
                    CancellationToken ct) =>
                {
                    var result = await handler.CreateWarehouseAsync(
                        request.Code,
                        request.Name,
                        Key(http),
                        context,
                        ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(
                            result.Error!,
                            context.CorrelationId.Value)
                        : Results.Created(
                            "/api/v1/inventory/warehouses/" +
                            result.Value!.EntityPublicId.ToString("D"),
                            result.Value);
                })
            .RequireAuthorization(InventoryPermissions.WarehouseManage)
            .WithName("CreateInventoryWarehouse");

        app.MapPut(
                "/api/v1/inventory/warehouses/{warehousePublicId:guid}",
                async (
                    Guid warehousePublicId,
                    EditWarehouseRequest request,
                    HttpRequest http,
                    IExecutionContext context,
                    InventoryMasterCommandHandler handler,
                    CancellationToken ct) =>
                {
                    var result = await handler.EditWarehouseAsync(
                        warehousePublicId,
                        request.Version,
                        request.Code,
                        request.Name,
                        Key(http),
                        context,
                        ct);
                    return Map(result, context);
                })
            .RequireAuthorization(InventoryPermissions.WarehouseManage)
            .WithName("EditInventoryWarehouse");

        app.MapPost(
                "/api/v1/inventory/warehouses/{warehousePublicId:guid}/state",
                async (
                    Guid warehousePublicId,
                    ChangeInventoryMasterStateRequest request,
                    HttpRequest http,
                    IExecutionContext context,
                    InventoryMasterCommandHandler handler,
                    CancellationToken ct) =>
                {
                    var result = await handler.ChangeWarehouseStateAsync(
                        warehousePublicId,
                        request.Version,
                        request.State,
                        Key(http),
                        context,
                        ct);
                    return Map(result, context);
                })
            .RequireAuthorization()
            .WithName("ChangeInventoryWarehouseState");

        app.MapGet(
                "/api/v1/inventory/locations",
                async (
                    Guid? warehousePublicId,
                    IExecutionContext context,
                    InventoryQueryHandler handler,
                    CancellationToken ct) =>
                {
                    var result = await handler.ListLocationsAsync(
                        warehousePublicId,
                        context,
                        ct);
                    return Map(result, context);
                })
            .RequireAuthorization(InventoryPermissions.LocationRead)
            .WithName("ListInventoryLocations");

        app.MapPost(
                "/api/v1/inventory/locations",
                async (
                    CreateLocationRequest request,
                    HttpRequest http,
                    IExecutionContext context,
                    InventoryMasterCommandHandler handler,
                    CancellationToken ct) =>
                {
                    var result = await handler.CreateLocationAsync(
                        request.WarehousePublicId,
                        request.ParentLocationPublicId,
                        request.Code,
                        request.Name,
                        request.StockBearing,
                        Key(http),
                        context,
                        ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(
                            result.Error!,
                            context.CorrelationId.Value)
                        : Results.Created(
                            "/api/v1/inventory/locations/" +
                            result.Value!.EntityPublicId.ToString("D"),
                            result.Value);
                })
            .RequireAuthorization(InventoryPermissions.LocationManage)
            .WithName("CreateInventoryLocation");

        app.MapPut(
                "/api/v1/inventory/locations/{locationPublicId:guid}",
                async (
                    Guid locationPublicId,
                    EditLocationRequest request,
                    HttpRequest http,
                    IExecutionContext context,
                    InventoryMasterCommandHandler handler,
                    CancellationToken ct) =>
                {
                    var result = await handler.EditLocationAsync(
                        locationPublicId,
                        request.Version,
                        request.ParentLocationPublicId,
                        request.Code,
                        request.Name,
                        request.StockBearing,
                        Key(http),
                        context,
                        ct);
                    return Map(result, context);
                })
            .RequireAuthorization(InventoryPermissions.LocationManage)
            .WithName("EditInventoryLocation");

        app.MapPost(
                "/api/v1/inventory/locations/{locationPublicId:guid}/state",
                async (
                    Guid locationPublicId,
                    ChangeInventoryMasterStateRequest request,
                    HttpRequest http,
                    IExecutionContext context,
                    InventoryMasterCommandHandler handler,
                    CancellationToken ct) =>
                {
                    var result = await handler.ChangeLocationStateAsync(
                        locationPublicId,
                        request.Version,
                        request.State,
                        Key(http),
                        context,
                        ct);
                    return Map(result, context);
                })
            .RequireAuthorization()
            .WithName("ChangeInventoryLocationState");

        app.MapGet(
                "/api/v1/inventory/stock",
                async (
                    Guid? productPublicId,
                    Guid? variantPublicId,
                    Guid? warehousePublicId,
                    Guid? locationPublicId,
                    string? disposition,
                    Guid? lotPublicId,
                    Guid? serialPublicId,
                    IExecutionContext context,
                    InventoryQueryHandler handler,
                    CancellationToken ct) =>
                {
                    if (!TryDisposition(disposition, out var dispositionCode))
                        return InvalidDisposition(context);

                    var result = await handler.ListStockAsync(
                        new InventoryReadFilter(
                            productPublicId,
                            variantPublicId,
                            warehousePublicId,
                            locationPublicId,
                            dispositionCode,
                            lotPublicId,
                            serialPublicId),
                        context,
                        ct);
                    return Map(result, context);
                })
            .RequireAuthorization(InventoryPermissions.StockRead)
            .WithName("ListInventoryStock");

        app.MapGet(
                "/api/v1/inventory/positions",
                async (
                    Guid? productPublicId,
                    Guid? variantPublicId,
                    Guid? warehousePublicId,
                    Guid? locationPublicId,
                    string? disposition,
                    Guid? lotPublicId,
                    Guid? serialPublicId,
                    IExecutionContext context,
                    InventoryQueryHandler handler,
                    CancellationToken ct) =>
                {
                    if (!TryDisposition(disposition, out var dispositionCode))
                        return InvalidDisposition(context);

                    var result = await handler.ListPositionsAsync(
                        new InventoryReadFilter(
                            productPublicId,
                            variantPublicId,
                            warehousePublicId,
                            locationPublicId,
                            dispositionCode,
                            lotPublicId,
                            serialPublicId),
                        context,
                        ct);
                    return Map(result, context);
                })
            .RequireAuthorization(InventoryPermissions.StockRead)
            .WithName("ListInventoryPositions");

        app.MapGet(
                "/api/v1/inventory/movements",
                async (
                    Guid? productPublicId,
                    Guid? variantPublicId,
                    Guid? warehousePublicId,
                    Guid? locationPublicId,
                    string? disposition,
                    Guid? lotPublicId,
                    Guid? serialPublicId,
                    IExecutionContext context,
                    InventoryQueryHandler handler,
                    CancellationToken ct) =>
                {
                    if (!TryDisposition(disposition, out var dispositionCode))
                        return InvalidDisposition(context);

                    var result = await handler.ListMovementsAsync(
                        new InventoryReadFilter(
                            productPublicId,
                            variantPublicId,
                            warehousePublicId,
                            locationPublicId,
                            dispositionCode,
                            lotPublicId,
                            serialPublicId),
                        context,
                        ct);
                    return Map(result, context);
                })
            .RequireAuthorization(InventoryPermissions.TraceRead)
            .WithName("ListInventoryMovements");

        app.MapGet(
                "/api/v1/inventory/lots",
                async (
                    Guid? productPublicId,
                    Guid? variantPublicId,
                    IExecutionContext context,
                    InventoryQueryHandler handler,
                    CancellationToken ct) =>
                {
                    var result = await handler.ListLotsAsync(
                        productPublicId,
                        variantPublicId,
                        context,
                        ct);
                    return Map(result, context);
                })
            .RequireAuthorization(InventoryPermissions.TraceRead)
            .WithName("ListInventoryLots");

        app.MapPost(
                "/api/v1/inventory/lots",
                async (
                    CreateInventoryLotRequest request,
                    HttpRequest http,
                    IExecutionContext context,
                    InventoryMasterCommandHandler handler,
                    CancellationToken ct) =>
                {
                    var result = await handler.CreateLotAsync(
                        request.ProductPublicId,
                        request.VariantPublicId,
                        request.Code,
                        request.ManufactureDate,
                        request.ExpiryDate,
                        Key(http),
                        context,
                        ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(
                            result.Error!,
                            context.CorrelationId.Value)
                        : Results.Created(
                            "/api/v1/inventory/lots/" +
                            result.Value!.EntityPublicId.ToString("D"),
                            result.Value);
                })
            .RequireAuthorization(InventoryPermissions.LotManageMetadata)
            .WithName("CreateInventoryLotMetadata");

        app.MapPut(
                "/api/v1/inventory/lots/{lotPublicId:guid}",
                async (
                    Guid lotPublicId,
                    UpdateInventoryLotRequest request,
                    HttpRequest http,
                    IExecutionContext context,
                    InventoryMasterCommandHandler handler,
                    CancellationToken ct) =>
                {
                    var result = await handler.UpdateLotAsync(
                        lotPublicId,
                        request.Version,
                        request.ManufactureDate,
                        request.ExpiryDate,
                        Key(http),
                        context,
                        ct);
                    return Map(result, context);
                })
            .RequireAuthorization(InventoryPermissions.LotManageMetadata)
            .WithName("UpdateInventoryLotMetadata");

        app.MapPost(
                "/api/v1/inventory/serials",
                async (
                    CreateInventorySerialRequest request,
                    HttpRequest http,
                    IExecutionContext context,
                    InventoryMasterCommandHandler handler,
                    CancellationToken ct) =>
                {
                    var result = await handler.CreateSerialAsync(
                        request.ProductPublicId,
                        request.VariantPublicId,
                        request.LotPublicId,
                        request.Value,
                        Key(http),
                        context,
                        ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(
                            result.Error!,
                            context.CorrelationId.Value)
                        : Results.Created(
                            "/api/v1/inventory/serials/" +
                            result.Value!.EntityPublicId.ToString("D"),
                            result.Value);
                })
            .RequireAuthorization(InventoryPermissions.SerialManageMetadata)
            .WithName("CreateInventorySerialMetadata");

        app.MapGet(
                "/api/v1/inventory/serials/{serialPublicId:guid}",
                async (
                    Guid serialPublicId,
                    IExecutionContext context,
                    InventoryQueryHandler handler,
                    CancellationToken ct) =>
                {
                    var result = await handler.GetSerialAsync(
                        serialPublicId,
                        context,
                        ct);
                    return Map(result, context);
                })
            .RequireAuthorization(InventoryPermissions.TraceRead)
            .WithName("GetInventorySerialTrace");

        app.MapGet(
                "/api/v1/inventory/reservations",
                async (
                    Guid? productPublicId,
                    Guid? warehousePublicId,
                    IExecutionContext context,
                    InventoryQueryHandler handler,
                    CancellationToken ct) =>
                {
                    var result = await handler.ListReservationsAsync(
                        productPublicId,
                        warehousePublicId,
                        context,
                        ct);
                    return Map(result, context);
                })
            .RequireAuthorization(InventoryPermissions.TraceRead)
            .WithName("ListInventoryReservations");

        app.MapPost(
                "/api/v1/inventory/warehouses/{warehousePublicId:guid}/access-grants",
                async (
                    Guid warehousePublicId,
                    WarehouseAccessGrantRequest request,
                    HttpRequest http,
                    IExecutionContext context,
                    IWarehouseAccessGrantAuthority authority,
                    CancellationToken ct) =>
                {
                    var result = await authority.GrantAsync(
                        request.ActorId,
                        warehousePublicId,
                        Key(http),
                        context,
                        ct);
                    return result.IsFailure
                        ? ApplicationErrorHttpMapper.ToResult(result.Error!, context.CorrelationId.Value)
                        : Results.Created(
                            "/api/v1/inventory/warehouses/" + warehousePublicId.ToString("D") +
                            "/access-grants/" + result.Value!.PublicId.ToString("D"),
                            result.Value);
                })
            .RequireAuthorization(InventoryPermissions.WarehouseManage)
            .WithName("GrantWarehouseAccess");

        app.MapPost(
                "/api/v1/inventory/warehouses/{warehousePublicId:guid}/access-grants/revoke",
                async (
                    Guid warehousePublicId,
                    WarehouseAccessGrantRequest request,
                    HttpRequest http,
                    IExecutionContext context,
                    IWarehouseAccessGrantAuthority authority,
                    CancellationToken ct) =>
                {
                    var result = await authority.RevokeAsync(
                        request.ActorId,
                        warehousePublicId,
                        Key(http),
                        context,
                        ct);
                    return Map(result, context);
                })
            .RequireAuthorization(InventoryPermissions.WarehouseManage)
            .WithName("RevokeWarehouseAccess");
    }

    private static IResult Map<T>(
        Mars.Application.Foundation.Results.Result<T> result,
        IExecutionContext context) =>
        result.IsFailure
            ? ApplicationErrorHttpMapper.ToResult(
                result.Error!,
                context.CorrelationId.Value)
            : Results.Ok(result.Value);

    private static string Key(HttpRequest request) =>
        request.Headers["Idempotency-Key"].ToString();

    private static bool TryDisposition(
        string? value,
        out InventoryDispositionCode? code)
    {
        code = value switch
        {
            null or "" => null,
            "AVAILABLE" => InventoryDispositionCode.Available,
            "QUARANTINE" => InventoryDispositionCode.Quarantine,
            "QUALITY_HOLD" => InventoryDispositionCode.QualityHold,
            "REWORK" => InventoryDispositionCode.Rework,
            "DAMAGED" => InventoryDispositionCode.Damaged,
            "TRANSIT" => InventoryDispositionCode.Transit,
            _ => null
        };

        return string.IsNullOrEmpty(value) || code.HasValue;
    }

    private static IResult InvalidDisposition(IExecutionContext context) =>
        Results.BadRequest(new
        {
            code = "inventory.disposition.invalid",
            message = "Disposition must be AVAILABLE, QUARANTINE, QUALITY_HOLD, REWORK, DAMAGED or TRANSIT.",
            category = "Validation",
            correlationId = context.CorrelationId.Value
        });
}
