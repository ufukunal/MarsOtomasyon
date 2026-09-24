using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Results;
using Mars.Domain.Inventory;
using InventoryWarehouse = Mars.Domain.Inventory.Warehouse;

namespace Mars.Application.Inventory;

public enum InventoryMutationOutcome
{
    Succeeded = 1,
    NotFound = 2,
    DuplicateOperation = 3,
    StaleVersion = 4,
    StateConflict = 5,
    DeterministicConflict = 6,
    BusinessConflict = 7,
    InsufficientStock = 8,
    TrackingConflict = 9
}

public sealed record InventoryMutationPersistenceResult(
    InventoryMutationOutcome Outcome,
    Guid? EntityPublicId = null,
    string? State = null,
    long? Version = null,
    decimal? BaseQuantity = null);

public sealed record InventoryMutationReceipt(
    Guid EntityPublicId,
    string EntityType,
    string State,
    long Version,
    string CorrelationId);

public sealed record InventoryMovementReceipt(
    Guid MovementPublicId,
    decimal BaseQuantity,
    string CorrelationId);

public sealed record InventoryReservationReceipt(
    Guid ReservationPublicId,
    decimal CurrentBaseQuantity,
    string CorrelationId);

public sealed record InventoryWriteContext(
    Guid CompanyId,
    IdempotencyOperation Idempotency,
    AuditEntry Audit);

public sealed record WarehouseView(
    Guid PublicId,
    string Code,
    string Name,
    string State,
    long Version);

public sealed record LocationView(
    Guid PublicId,
    Guid WarehousePublicId,
    Guid? ParentLocationPublicId,
    string Code,
    string Name,
    bool StockBearing,
    string State,
    long Version);

public sealed record InventoryStockSummaryView(
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid WarehousePublicId,
    decimal OnHand,
    decimal AvailableOnHand,
    decimal Reserved,
    decimal AvailableToReserve);

public sealed record InventoryPositionBalanceView(
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid WarehousePublicId,
    Guid? LocationPublicId,
    string Disposition,
    Guid? LotPublicId,
    Guid? SerialPublicId,
    decimal Quantity);

public sealed record InventoryMovementView(
    Guid PublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal EnteredQuantity,
    decimal ConversionFactorSnapshot,
    decimal BaseQuantity,
    Guid? SourceWarehousePublicId,
    Guid? SourceLocationPublicId,
    string? SourceDisposition,
    Guid? TargetWarehousePublicId,
    Guid? TargetLocationPublicId,
    string? TargetDisposition,
    Guid? LotPublicId,
    Guid? SerialPublicId,
    string SourceModule,
    string SourceEntityType,
    Guid SourceDocumentPublicId,
    Guid? SourceLinePublicId,
    Guid? ReversalOfMovementPublicId,
    DateTimeOffset PostedAt,
    Guid ActorId,
    string CorrelationId);

public sealed record LotTraceView(
    Guid PublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    string Code,
    DateOnly? ManufactureDate,
    DateOnly? ExpiryDate,
    decimal OnHand,
    long Version);

public sealed record SerialTraceView(
    Guid PublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid? LotPublicId,
    string Value,
    Guid? WarehousePublicId,
    Guid? LocationPublicId,
    string? Disposition,
    bool OnHand,
    long Version,
    IReadOnlyList<InventoryMovementView> Movements);

public sealed record ReservationMovementView(
    Guid PublicId,
    string Kind,
    decimal EnteredQuantity,
    decimal BaseQuantity,
    string? SourceModule,
    Guid? SourceDocumentPublicId,
    Guid? SourceLinePublicId,
    Guid ActorId,
    string CorrelationId,
    DateTimeOffset OccurredAt);

public sealed record ReservationView(
    Guid PublicId,
    Guid SalesOrderPublicId,
    long SalesOrderVersion,
    Guid SalesOrderLinePublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid WarehousePublicId,
    Guid UomPublicId,
    decimal ConversionFactorSnapshot,
    decimal CurrentBaseQuantity,
    DateTimeOffset CreatedAt,
    IReadOnlyList<ReservationMovementView> Movements);

public sealed record InventoryReadFilter(
    Guid? ProductPublicId = null,
    Guid? VariantPublicId = null,
    Guid? WarehousePublicId = null,
    Guid? LocationPublicId = null,
    InventoryDispositionCode? Disposition = null,
    Guid? LotPublicId = null,
    Guid? SerialPublicId = null);

public interface IInventoryReadPersistence
{
    Task<IReadOnlyList<WarehouseView>> ListWarehousesAsync(
        Guid companyId,
        string? search,
        CancellationToken cancellationToken);

    Task<IReadOnlyList<LocationView>> ListLocationsAsync(
        Guid companyId,
        Guid? warehousePublicId,
        CancellationToken cancellationToken);

    Task<IReadOnlyList<InventoryStockSummaryView>> ListStockAsync(
        Guid companyId,
        InventoryReadFilter filter,
        CancellationToken cancellationToken);

    Task<IReadOnlyList<InventoryPositionBalanceView>> ListPositionsAsync(
        Guid companyId,
        InventoryReadFilter filter,
        CancellationToken cancellationToken);

    Task<IReadOnlyList<InventoryMovementView>> ListMovementsAsync(
        Guid companyId,
        InventoryReadFilter filter,
        CancellationToken cancellationToken);

    Task<IReadOnlyList<LotTraceView>> ListLotsAsync(
        Guid companyId,
        Guid? productPublicId,
        Guid? variantPublicId,
        CancellationToken cancellationToken);

    Task<SerialTraceView?> GetSerialAsync(
        Guid companyId,
        Guid serialPublicId,
        CancellationToken cancellationToken);

    Task<IReadOnlyList<ReservationView>> ListReservationsAsync(
        Guid companyId,
        Guid? productPublicId,
        Guid? warehousePublicId,
        CancellationToken cancellationToken);
}

public sealed record CreateWarehouseWrite(InventoryWarehouse Warehouse, InventoryWriteContext Context);
public sealed record EditWarehouseWrite(
    Guid WarehousePublicId,
    long ExpectedVersion,
    string Code,
    string Name,
    InventoryWriteContext Context);
public sealed record ChangeWarehouseStateWrite(
    Guid WarehousePublicId,
    long ExpectedVersion,
    InventoryMasterState TargetState,
    InventoryWriteContext Context);

public sealed record CreateLocationWrite(Location Location, InventoryWriteContext Context);
public sealed record EditLocationWrite(
    Guid LocationPublicId,
    long ExpectedVersion,
    Guid? ParentLocationPublicId,
    string Code,
    string Name,
    bool StockBearing,
    InventoryWriteContext Context);
public sealed record ChangeLocationStateWrite(
    Guid LocationPublicId,
    long ExpectedVersion,
    InventoryMasterState TargetState,
    InventoryWriteContext Context);

public sealed record CreateLotWrite(Lot Lot, InventoryWriteContext Context);
public sealed record UpdateLotWrite(
    Guid LotPublicId,
    long ExpectedVersion,
    DateOnly? ManufactureDate,
    DateOnly? ExpiryDate,
    InventoryWriteContext Context);
public sealed record CreateSerialWrite(Serial Serial, InventoryWriteContext Context);

public interface IInventoryMutationPersistence
{
    Task<InventoryMutationPersistenceResult> CreateWarehouseAsync(
        CreateWarehouseWrite write,
        CancellationToken cancellationToken);

    Task<InventoryMutationPersistenceResult> EditWarehouseAsync(
        EditWarehouseWrite write,
        CancellationToken cancellationToken);

    Task<InventoryMutationPersistenceResult> ChangeWarehouseStateAsync(
        ChangeWarehouseStateWrite write,
        CancellationToken cancellationToken);

    Task<InventoryMutationPersistenceResult> CreateLocationAsync(
        CreateLocationWrite write,
        CancellationToken cancellationToken);

    Task<InventoryMutationPersistenceResult> EditLocationAsync(
        EditLocationWrite write,
        CancellationToken cancellationToken);

    Task<InventoryMutationPersistenceResult> ChangeLocationStateAsync(
        ChangeLocationStateWrite write,
        CancellationToken cancellationToken);

    Task<InventoryMutationPersistenceResult> CreateLotAsync(
        CreateLotWrite write,
        CancellationToken cancellationToken);

    Task<InventoryMutationPersistenceResult> UpdateLotAsync(
        UpdateLotWrite write,
        CancellationToken cancellationToken);

    Task<InventoryMutationPersistenceResult> CreateSerialAsync(
        CreateSerialWrite write,
        CancellationToken cancellationToken);
}

public sealed record InventoryMovementCommand(
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal EnteredQuantity,
    decimal ConversionFactorSnapshot,
    InventoryPosition? Source,
    InventoryPosition? Target,
    InventorySourceIdentity SourceIdentity,
    Guid? ReversalOfMovementPublicId,
    string OperationKey);

public sealed record PostInventoryMovementWrite(
    Guid PublicId,
    InventoryMovementCommand Command,
    InventoryWriteContext Context);

public sealed record CreateReservationCommand(
    Guid SalesOrderPublicId,
    long SalesOrderVersion,
    Guid SalesOrderLinePublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid WarehousePublicId,
    Guid UomPublicId,
    decimal EnteredQuantity,
    decimal ConversionFactorSnapshot,
    string OperationKey);

public sealed record ChangeReservationCommand(
    Guid ReservationPublicId,
    decimal EnteredQuantity,
    InventorySourceIdentity? SourceIdentity,
    string OperationKey);

public sealed record CreateReservationWrite(
    Guid PublicId,
    CreateReservationCommand Command,
    InventoryWriteContext Context);

public sealed record ChangeReservationWrite(
    Guid ReservationPublicId,
    ReservationMovementKind Kind,
    ChangeReservationCommand Command,
    InventoryWriteContext Context);

public interface IInventoryOperationalBlocker
{
    Task<bool> HasOpenWarehouseWorkAsync(Guid companyId,Guid warehousePublicId,CancellationToken cancellationToken);
    Task<bool> HasOpenLocationWorkAsync(Guid companyId,Guid locationPublicId,CancellationToken cancellationToken);
}

public interface IInventoryAuthorityPersistence
{
    Task<InventoryMutationPersistenceResult> PostMovementAsync(
        PostInventoryMovementWrite write,
        CancellationToken cancellationToken);

    Task<InventoryMutationPersistenceResult> CreateReservationAsync(
        CreateReservationWrite write,
        CancellationToken cancellationToken);

    Task<InventoryMutationPersistenceResult> ChangeReservationAsync(
        ChangeReservationWrite write,
        CancellationToken cancellationToken);
}

public sealed class InventoryQueryHandler(
    IPermissionEvaluator permissionEvaluator,
    IInventoryReadPersistence persistence)
{
    public async Task<Result<IReadOnlyList<WarehouseView>>> ListWarehousesAsync(
        string? search,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        if (!await GrantedAsync(InventoryPermissions.WarehouseRead, context, cancellationToken))
            return Result<IReadOnlyList<WarehouseView>>.Failure(Denied("read Warehouses"));

        return Result<IReadOnlyList<WarehouseView>>.Success(
            await persistence.ListWarehousesAsync(
                context.CompanyId,
                string.IsNullOrWhiteSpace(search) ? null : search.Trim(),
                cancellationToken));
    }

    public async Task<Result<IReadOnlyList<LocationView>>> ListLocationsAsync(
        Guid? warehousePublicId,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        if (warehousePublicId == Guid.Empty)
            return Result<IReadOnlyList<LocationView>>.Failure(
                Validation("inventory.location.warehouse_invalid", "Warehouse id cannot be empty."));
        if (!await GrantedAsync(InventoryPermissions.LocationRead, context, cancellationToken))
            return Result<IReadOnlyList<LocationView>>.Failure(Denied("read Warehouse Locations"));

        return Result<IReadOnlyList<LocationView>>.Success(
            await persistence.ListLocationsAsync(context.CompanyId, warehousePublicId, cancellationToken));
    }

    public async Task<Result<IReadOnlyList<InventoryStockSummaryView>>> ListStockAsync(
        InventoryReadFilter filter,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(filter);
        if (!await GrantedAsync(InventoryPermissions.StockRead, context, cancellationToken))
            return Result<IReadOnlyList<InventoryStockSummaryView>>.Failure(Denied("read Inventory stock"));

        return Result<IReadOnlyList<InventoryStockSummaryView>>.Success(
            await persistence.ListStockAsync(context.CompanyId, filter, cancellationToken));
    }

    public async Task<Result<IReadOnlyList<InventoryPositionBalanceView>>> ListPositionsAsync(
        InventoryReadFilter filter,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(filter);
        if (!await GrantedAsync(InventoryPermissions.StockRead, context, cancellationToken))
            return Result<IReadOnlyList<InventoryPositionBalanceView>>.Failure(Denied("read Inventory positions"));

        return Result<IReadOnlyList<InventoryPositionBalanceView>>.Success(
            await persistence.ListPositionsAsync(context.CompanyId, filter, cancellationToken));
    }

    public async Task<Result<IReadOnlyList<InventoryMovementView>>> ListMovementsAsync(
        InventoryReadFilter filter,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(filter);
        if (!await GrantedAsync(InventoryPermissions.TraceRead, context, cancellationToken))
            return Result<IReadOnlyList<InventoryMovementView>>.Failure(Denied("read Inventory movement history"));

        return Result<IReadOnlyList<InventoryMovementView>>.Success(
            await persistence.ListMovementsAsync(context.CompanyId, filter, cancellationToken));
    }

    public async Task<Result<IReadOnlyList<LotTraceView>>> ListLotsAsync(
        Guid? productPublicId,
        Guid? variantPublicId,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        if (productPublicId == Guid.Empty || variantPublicId == Guid.Empty)
            return Result<IReadOnlyList<LotTraceView>>.Failure(
                Validation("inventory.trace.identity_invalid", "Optional Product/Variant ids cannot be empty."));
        if (!await GrantedAsync(InventoryPermissions.TraceRead, context, cancellationToken))
            return Result<IReadOnlyList<LotTraceView>>.Failure(Denied("read Inventory lot trace"));

        return Result<IReadOnlyList<LotTraceView>>.Success(
            await persistence.ListLotsAsync(
                context.CompanyId,
                productPublicId,
                variantPublicId,
                cancellationToken));
    }

    public async Task<Result<SerialTraceView>> GetSerialAsync(
        Guid serialPublicId,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        if (serialPublicId == Guid.Empty)
            return Result<SerialTraceView>.Failure(
                Validation("inventory.trace.serial_required", "Serial id is required."));
        if (!await GrantedAsync(InventoryPermissions.TraceRead, context, cancellationToken))
            return Result<SerialTraceView>.Failure(Denied("read Inventory serial trace"));

        var serial = await persistence.GetSerialAsync(
            context.CompanyId,
            serialPublicId,
            cancellationToken);

        return serial is null
            ? Result<SerialTraceView>.Failure(
                new ApplicationError(
                    ErrorCategory.NotFound,
                    "inventory.trace.serial_not_found",
                    "Serial was not found in the current company."))
            : Result<SerialTraceView>.Success(serial);
    }

    public async Task<Result<IReadOnlyList<ReservationView>>> ListReservationsAsync(
        Guid? productPublicId,
        Guid? warehousePublicId,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        if (productPublicId == Guid.Empty || warehousePublicId == Guid.Empty)
            return Result<IReadOnlyList<ReservationView>>.Failure(
                Validation("inventory.reservation.identity_invalid", "Optional Product/Warehouse ids cannot be empty."));
        if (!await GrantedAsync(InventoryPermissions.TraceRead, context, cancellationToken))
            return Result<IReadOnlyList<ReservationView>>.Failure(Denied("read Inventory Reservations"));

        return Result<IReadOnlyList<ReservationView>>.Success(
            await persistence.ListReservationsAsync(
                context.CompanyId,
                productPublicId,
                warehousePublicId,
                cancellationToken));
    }

    private Task<bool> GrantedAsync(
        string permission,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(context);
        return permissionEvaluator.IsGrantedAsync(
            context.ActorId,
            context.CompanyId,
            permission,
            cancellationToken);
    }

    private static ApplicationError Denied(string action) =>
        new(
            ErrorCategory.Authorization,
            "authorization.permission_denied",
            $"The current actor is not authorized to {action}.");

    private static ApplicationError Validation(string code, string message) =>
        new(ErrorCategory.Validation, code, message);
}

public sealed class InventoryMasterCommandHandler(
    IPermissionEvaluator permissionEvaluator,
    IInventoryMutationPersistence persistence,
    IInventoryOperationalBlocker? operationalBlocker = null)
{
    private const int MaxOperationKeyLength = 128;

    public async Task<Result<InventoryMutationReceipt>> CreateWarehouseAsync(
        string code,
        string name,
        string operationKey,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(InventoryPermissions.WarehouseManage, context, cancellationToken))
            return Denied("manage Warehouses");

        var normalizedCode = Required(code);
        var normalizedName = Required(name);
        if (normalizedCode is null || normalizedName is null)
            return Validation("inventory.warehouse.invalid", "Warehouse code and name are required and must be trimmed.");

        var publicId = Guid.NewGuid();
        var writeContext = CreateContext(
            "inventory.warehouse.create",
            operationKey,
            "WarehouseCreated",
            "Warehouse",
            publicId,
            context);
        if (writeContext.Error is not null)
            return Result<InventoryMutationReceipt>.Failure(writeContext.Error);

        var warehouse = InventoryWarehouse.Create(
            publicId,
            context.CompanyId,
            normalizedCode,
            normalizedName,
            writeContext.Context!.Audit.OccurredAt);

        return Map(
            await persistence.CreateWarehouseAsync(
                new CreateWarehouseWrite(warehouse, writeContext.Context),
                cancellationToken),
            "Warehouse",
            context,
            "inventory.warehouse");
    }

    public async Task<Result<InventoryMutationReceipt>> EditWarehouseAsync(
        Guid warehousePublicId,
        long expectedVersion,
        string code,
        string name,
        string operationKey,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(InventoryPermissions.WarehouseManage, context, cancellationToken))
            return Denied("manage Warehouses");
        if (warehousePublicId == Guid.Empty || expectedVersion <= 0)
            return Validation("inventory.warehouse.identity_invalid", "Warehouse id and positive expected version are required.");

        var normalizedCode = Required(code);
        var normalizedName = Required(name);
        if (normalizedCode is null || normalizedName is null)
            return Validation("inventory.warehouse.invalid", "Warehouse code and name are required and must be trimmed.");

        var writeContext = CreateContext(
            "inventory.warehouse.edit",
            operationKey,
            "WarehouseChanged",
            "Warehouse",
            warehousePublicId,
            context);
        if (writeContext.Error is not null)
            return Result<InventoryMutationReceipt>.Failure(writeContext.Error);

        return Map(
            await persistence.EditWarehouseAsync(
                new EditWarehouseWrite(
                    warehousePublicId,
                    expectedVersion,
                    normalizedCode,
                    normalizedName,
                    writeContext.Context!),
                cancellationToken),
            "Warehouse",
            context,
            "inventory.warehouse");
    }

    public async Task<Result<InventoryMutationReceipt>> ChangeWarehouseStateAsync(
        Guid warehousePublicId,
        long expectedVersion,
        string state,
        string operationKey,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        var target = ParseState(state);
        if (target is null)
            return Validation("inventory.warehouse.state_invalid", "Warehouse state must be ACTIVE or INACTIVE.");

        var permission = target == InventoryMasterState.Inactive
            ? InventoryPermissions.WarehouseDeactivate
            : InventoryPermissions.WarehouseManage;
        if (!await AuthorizedAsync(permission, context, cancellationToken))
            return Denied(target == InventoryMasterState.Inactive ? "deactivate Warehouses" : "reactivate Warehouses");
        if (warehousePublicId == Guid.Empty || expectedVersion <= 0)
            return Validation("inventory.warehouse.identity_invalid", "Warehouse id and positive expected version are required.");
        if (target == InventoryMasterState.Inactive &&
            operationalBlocker is not null &&
            await operationalBlocker.HasOpenWarehouseWorkAsync(context.CompanyId,warehousePublicId,cancellationToken))
            return Validation("inventory.warehouse.open_work","Warehouse cannot be deactivated while Warehouse operational work remains open.");

        var writeContext = CreateContext(
            "inventory.warehouse.state",
            operationKey,
            target == InventoryMasterState.Active ? "WarehouseActivated" : "WarehouseDeactivated",
            "Warehouse",
            warehousePublicId,
            context);
        if (writeContext.Error is not null)
            return Result<InventoryMutationReceipt>.Failure(writeContext.Error);

        return Map(
            await persistence.ChangeWarehouseStateAsync(
                new ChangeWarehouseStateWrite(
                    warehousePublicId,
                    expectedVersion,
                    target.Value,
                    writeContext.Context!),
                cancellationToken),
            "Warehouse",
            context,
            "inventory.warehouse");
    }

    public async Task<Result<InventoryMutationReceipt>> CreateLocationAsync(
        Guid warehousePublicId,
        Guid? parentLocationPublicId,
        string code,
        string name,
        bool stockBearing,
        string operationKey,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(InventoryPermissions.LocationManage, context, cancellationToken))
            return Denied("manage Warehouse Locations");
        if (warehousePublicId == Guid.Empty || parentLocationPublicId == Guid.Empty)
            return Validation("inventory.location.identity_invalid", "Warehouse id is required and optional parent id cannot be empty.");

        var normalizedCode = Required(code);
        var normalizedName = Required(name);
        if (normalizedCode is null || normalizedName is null)
            return Validation("inventory.location.invalid", "Location code and name are required and must be trimmed.");

        var publicId = Guid.NewGuid();
        var writeContext = CreateContext(
            "inventory.location.create",
            operationKey,
            "LocationCreated",
            "Location",
            publicId,
            context);
        if (writeContext.Error is not null)
            return Result<InventoryMutationReceipt>.Failure(writeContext.Error);

        var location = Location.Create(
            publicId,
            context.CompanyId,
            warehousePublicId,
            parentLocationPublicId,
            normalizedCode,
            normalizedName,
            stockBearing,
            writeContext.Context!.Audit.OccurredAt);

        return Map(
            await persistence.CreateLocationAsync(
                new CreateLocationWrite(location, writeContext.Context),
                cancellationToken),
            "Location",
            context,
            "inventory.location");
    }

    public async Task<Result<InventoryMutationReceipt>> EditLocationAsync(
        Guid locationPublicId,
        long expectedVersion,
        Guid? parentLocationPublicId,
        string code,
        string name,
        bool stockBearing,
        string operationKey,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(InventoryPermissions.LocationManage, context, cancellationToken))
            return Denied("manage Warehouse Locations");
        if (locationPublicId == Guid.Empty || parentLocationPublicId == Guid.Empty || expectedVersion <= 0)
            return Validation("inventory.location.identity_invalid", "Location id and positive expected version are required; optional parent id cannot be empty.");

        var normalizedCode = Required(code);
        var normalizedName = Required(name);
        if (normalizedCode is null || normalizedName is null)
            return Validation("inventory.location.invalid", "Location code and name are required and must be trimmed.");

        var writeContext = CreateContext(
            "inventory.location.edit",
            operationKey,
            "LocationChanged",
            "Location",
            locationPublicId,
            context);
        if (writeContext.Error is not null)
            return Result<InventoryMutationReceipt>.Failure(writeContext.Error);

        return Map(
            await persistence.EditLocationAsync(
                new EditLocationWrite(
                    locationPublicId,
                    expectedVersion,
                    parentLocationPublicId,
                    normalizedCode,
                    normalizedName,
                    stockBearing,
                    writeContext.Context!),
                cancellationToken),
            "Location",
            context,
            "inventory.location");
    }

    public async Task<Result<InventoryMutationReceipt>> ChangeLocationStateAsync(
        Guid locationPublicId,
        long expectedVersion,
        string state,
        string operationKey,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        var target = ParseState(state);
        if (target is null)
            return Validation("inventory.location.state_invalid", "Location state must be ACTIVE or INACTIVE.");

        var permission = target == InventoryMasterState.Inactive
            ? InventoryPermissions.LocationDeactivate
            : InventoryPermissions.LocationManage;
        if (!await AuthorizedAsync(permission, context, cancellationToken))
            return Denied(target == InventoryMasterState.Inactive ? "deactivate Warehouse Locations" : "reactivate Warehouse Locations");
        if (locationPublicId == Guid.Empty || expectedVersion <= 0)
            return Validation("inventory.location.identity_invalid", "Location id and positive expected version are required.");
        if (target == InventoryMasterState.Inactive &&
            operationalBlocker is not null &&
            await operationalBlocker.HasOpenLocationWorkAsync(context.CompanyId,locationPublicId,cancellationToken))
            return Validation("inventory.location.open_work","Location cannot be deactivated while Warehouse operational work remains open.");

        var writeContext = CreateContext(
            "inventory.location.state",
            operationKey,
            target == InventoryMasterState.Active ? "LocationActivated" : "LocationDeactivated",
            "Location",
            locationPublicId,
            context);
        if (writeContext.Error is not null)
            return Result<InventoryMutationReceipt>.Failure(writeContext.Error);

        return Map(
            await persistence.ChangeLocationStateAsync(
                new ChangeLocationStateWrite(
                    locationPublicId,
                    expectedVersion,
                    target.Value,
                    writeContext.Context!),
                cancellationToken),
            "Location",
            context,
            "inventory.location");
    }

    public async Task<Result<InventoryMutationReceipt>> CreateLotAsync(
        Guid productPublicId,
        Guid? variantPublicId,
        string code,
        DateOnly? manufactureDate,
        DateOnly? expiryDate,
        string operationKey,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(InventoryPermissions.LotManageMetadata, context, cancellationToken))
            return Denied("manage Inventory Lot metadata");
        if (productPublicId == Guid.Empty || variantPublicId == Guid.Empty)
            return Validation("inventory.lot.identity_invalid", "Product id is required and optional Variant id cannot be empty.");

        var normalizedCode = Required(code);
        if (normalizedCode is null)
            return Validation("inventory.lot.invalid", "Lot code is required and must be trimmed.");
        if (manufactureDate.HasValue && expiryDate.HasValue && expiryDate.Value < manufactureDate.Value)
            return Validation("inventory.lot.date_invalid", "Expiry date cannot be before manufacture date.");

        var publicId = Guid.NewGuid();
        var writeContext = CreateContext(
            "inventory.lot.create",
            operationKey,
            "InventoryLotCreated",
            "InventoryLot",
            publicId,
            context);
        if (writeContext.Error is not null)
            return Result<InventoryMutationReceipt>.Failure(writeContext.Error);

        var lot = Lot.Create(
            publicId,
            context.CompanyId,
            productPublicId,
            variantPublicId,
            normalizedCode,
            manufactureDate,
            expiryDate,
            writeContext.Context!.Audit.OccurredAt);

        return Map(
            await persistence.CreateLotAsync(
                new CreateLotWrite(lot, writeContext.Context),
                cancellationToken),
            "InventoryLot",
            context,
            "inventory.lot");
    }

    public async Task<Result<InventoryMutationReceipt>> UpdateLotAsync(
        Guid lotPublicId,
        long expectedVersion,
        DateOnly? manufactureDate,
        DateOnly? expiryDate,
        string operationKey,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(InventoryPermissions.LotManageMetadata, context, cancellationToken))
            return Denied("manage Inventory Lot metadata");
        if (lotPublicId == Guid.Empty || expectedVersion <= 0)
            return Validation("inventory.lot.identity_invalid", "Lot id and positive expected version are required.");
        if (manufactureDate.HasValue && expiryDate.HasValue && expiryDate.Value < manufactureDate.Value)
            return Validation("inventory.lot.date_invalid", "Expiry date cannot be before manufacture date.");

        var writeContext = CreateContext(
            "inventory.lot.edit",
            operationKey,
            "InventoryLotMetadataChanged",
            "InventoryLot",
            lotPublicId,
            context);
        if (writeContext.Error is not null)
            return Result<InventoryMutationReceipt>.Failure(writeContext.Error);

        return Map(
            await persistence.UpdateLotAsync(
                new UpdateLotWrite(
                    lotPublicId,
                    expectedVersion,
                    manufactureDate,
                    expiryDate,
                    writeContext.Context!),
                cancellationToken),
            "InventoryLot",
            context,
            "inventory.lot");
    }

    public async Task<Result<InventoryMutationReceipt>> CreateSerialAsync(
        Guid productPublicId,
        Guid? variantPublicId,
        Guid? lotPublicId,
        string value,
        string operationKey,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(InventoryPermissions.SerialManageMetadata, context, cancellationToken))
            return Denied("manage Inventory Serial metadata");
        if (productPublicId == Guid.Empty || variantPublicId == Guid.Empty || lotPublicId == Guid.Empty)
            return Validation("inventory.serial.identity_invalid", "Product id is required and optional Variant/Lot ids cannot be empty.");

        var normalizedValue = Required(value);
        if (normalizedValue is null)
            return Validation("inventory.serial.invalid", "Serial value is required and must be trimmed.");

        var publicId = Guid.NewGuid();
        var writeContext = CreateContext(
            "inventory.serial.create",
            operationKey,
            "InventorySerialCreated",
            "InventorySerial",
            publicId,
            context);
        if (writeContext.Error is not null)
            return Result<InventoryMutationReceipt>.Failure(writeContext.Error);

        var serial = Serial.Create(
            publicId,
            context.CompanyId,
            productPublicId,
            variantPublicId,
            lotPublicId,
            normalizedValue,
            writeContext.Context!.Audit.OccurredAt);

        return Map(
            await persistence.CreateSerialAsync(
                new CreateSerialWrite(serial, writeContext.Context),
                cancellationToken),
            "InventorySerial",
            context,
            "inventory.serial");
    }

    private Task<bool> AuthorizedAsync(
        string permission,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(context);
        return permissionEvaluator.IsGrantedAsync(
            context.ActorId,
            context.CompanyId,
            permission,
            cancellationToken);
    }

    internal static (InventoryWriteContext? Context, ApplicationError? Error) CreateContext(
        string scope,
        string operationKey,
        string action,
        string entityType,
        Guid entityPublicId,
        IExecutionContext context)
    {
        var key = operationKey ?? string.Empty;
        if (string.IsNullOrWhiteSpace(key))
            return (
                null,
                new ApplicationError(
                    ErrorCategory.Validation,
                    scope + ".idempotency_key_required",
                    "Idempotency-Key is required."));

        if (!string.Equals(key, key.Trim(), StringComparison.Ordinal) ||
            key.Length > MaxOperationKeyLength)
            return (
                null,
                new ApplicationError(
                    ErrorCategory.Validation,
                    scope + ".idempotency_key_invalid",
                    $"Idempotency-Key must be trimmed and cannot exceed {MaxOperationKeyLength} characters."));

        var now = DateTimeOffset.UtcNow;
        return (
            new InventoryWriteContext(
                context.CompanyId,
                new IdempotencyOperation(
                    scope + ":" + context.CompanyId.ToString("D"),
                    key,
                    null,
                    now),
                new AuditEntry(
                    context.ActorId,
                    context.CompanyId,
                    context.BranchId,
                    context.CorrelationId.Value,
                    "Inventory",
                    action,
                    entityType,
                    entityPublicId,
                    null,
                    now)),
            null);
    }

    internal static Result<InventoryMutationReceipt> Map(
        InventoryMutationPersistenceResult result,
        string entityType,
        IExecutionContext context,
        string codePrefix)
    {
        if (result.Outcome != InventoryMutationOutcome.Succeeded)
            return Result<InventoryMutationReceipt>.Failure(MapError(result.Outcome, codePrefix));

        return Result<InventoryMutationReceipt>.Success(
            new InventoryMutationReceipt(
                result.EntityPublicId ?? throw new InvalidOperationException("Inventory mutation did not return an entity id."),
                entityType,
                result.State ?? "ACTIVE",
                result.Version ?? throw new InvalidOperationException("Inventory mutation did not return a version."),
                context.CorrelationId.Value));
    }

    internal static ApplicationError MapError(
        InventoryMutationOutcome outcome,
        string codePrefix) => outcome switch
        {
            InventoryMutationOutcome.NotFound =>
                new(ErrorCategory.NotFound, codePrefix + ".not_found", "The requested Inventory record was not found in the current company."),
            InventoryMutationOutcome.DuplicateOperation =>
                new(ErrorCategory.Conflict, codePrefix + ".duplicate_operation", "The Idempotency-Key has already been used for this Inventory operation in the current company."),
            InventoryMutationOutcome.StaleVersion =>
                new(ErrorCategory.Concurrency, codePrefix + ".stale_version", "The Inventory record changed after the supplied version was read."),
            InventoryMutationOutcome.StateConflict =>
                new(ErrorCategory.Conflict, codePrefix + ".state_conflict", "The Inventory record is already in, or cannot enter, the requested state."),
            InventoryMutationOutcome.DeterministicConflict =>
                new(ErrorCategory.Conflict, codePrefix + ".deterministic_conflict", "A deterministic Inventory uniqueness conflict must be resolved."),
            InventoryMutationOutcome.BusinessConflict =>
                new(ErrorCategory.BusinessRule, codePrefix + ".business_conflict", "The requested Inventory operation violates a frozen Inventory invariant."),
            InventoryMutationOutcome.InsufficientStock =>
                new(ErrorCategory.BusinessRule, codePrefix + ".insufficient_stock", "The Inventory operation would create negative physical stock."),
            InventoryMutationOutcome.TrackingConflict =>
                new(ErrorCategory.BusinessRule, codePrefix + ".tracking_conflict", "The Lot/Serial identity does not satisfy the Product tracking contract."),
            _ =>
                new(ErrorCategory.Infrastructure, codePrefix + ".unexpected", "The Inventory operation returned an unexpected persistence result.")
        };

    private static InventoryMasterState? ParseState(string value) => value switch
    {
        "ACTIVE" => InventoryMasterState.Active,
        "INACTIVE" => InventoryMasterState.Inactive,
        _ => null
    };

    private static string? Required(string? value) =>
        string.IsNullOrWhiteSpace(value) ||
        !string.Equals(value, value.Trim(), StringComparison.Ordinal)
            ? null
            : value;

    private static Result<InventoryMutationReceipt> Validation(string code, string message) =>
        Result<InventoryMutationReceipt>.Failure(
            new ApplicationError(ErrorCategory.Validation, code, message));

    private static Result<InventoryMutationReceipt> Denied(string action) =>
        Result<InventoryMutationReceipt>.Failure(
            new ApplicationError(
                ErrorCategory.Authorization,
                "authorization.permission_denied",
                $"The current actor is not authorized to {action}."));
}

public interface IInventoryPhysicalAuthority
{
    Task<Result<InventoryMovementReceipt>> PostAsync(
        InventoryMovementCommand command,
        IExecutionContext context,
        CancellationToken cancellationToken);
}

public interface IInventoryReservationAuthority
{
    Task<Result<InventoryReservationReceipt>> CreateAsync(
        CreateReservationCommand command,
        IExecutionContext context,
        CancellationToken cancellationToken);

    Task<Result<InventoryReservationReceipt>> IncreaseAsync(
        ChangeReservationCommand command,
        IExecutionContext context,
        CancellationToken cancellationToken);

    Task<Result<InventoryReservationReceipt>> ReleaseAsync(
        ChangeReservationCommand command,
        IExecutionContext context,
        CancellationToken cancellationToken);

    Task<Result<InventoryReservationReceipt>> ConsumeAsync(
        ChangeReservationCommand command,
        IExecutionContext context,
        CancellationToken cancellationToken);
}

public sealed class InventoryAuthorityService(IInventoryAuthorityPersistence persistence)
    : IInventoryPhysicalAuthority, IInventoryReservationAuthority
{
    public async Task<Result<InventoryMovementReceipt>> PostAsync(
        InventoryMovementCommand command,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(command);
        ArgumentNullException.ThrowIfNull(context);

        if (command.ProductPublicId == Guid.Empty ||
            command.VariantPublicId == Guid.Empty ||
            command.UomPublicId == Guid.Empty ||
            command.EnteredQuantity <= 0m ||
            command.ConversionFactorSnapshot <= 0m ||
            (command.Source is null && command.Target is null) ||
            command.ReversalOfMovementPublicId == Guid.Empty)
            return Result<InventoryMovementReceipt>.Failure(
                new ApplicationError(
                    ErrorCategory.Validation,
                    "inventory.movement.invalid",
                    "Product/UOM, positive quantity and at least one physical side are required; optional ids cannot be empty."));

        if (command.SourceIdentity is null)
            return Result<InventoryMovementReceipt>.Failure(
                new ApplicationError(
                    ErrorCategory.Validation,
                    "inventory.movement.source_required",
                    "Owning source identity is required."));

        var publicId = Guid.NewGuid();
        var writeContext = InventoryMasterCommandHandler.CreateContext(
            "inventory.movement.post",
            command.OperationKey,
            "InventoryMovementPosted",
            "InventoryMovement",
            publicId,
            context);
        if (writeContext.Error is not null)
            return Result<InventoryMovementReceipt>.Failure(writeContext.Error);

        var result = await persistence.PostMovementAsync(
            new PostInventoryMovementWrite(
                publicId,
                command,
                writeContext.Context!),
            cancellationToken);

        return result.Outcome == InventoryMutationOutcome.Succeeded
            ? Result<InventoryMovementReceipt>.Success(
                new InventoryMovementReceipt(
                    result.EntityPublicId ?? publicId,
                    result.BaseQuantity ?? throw new InvalidOperationException("Inventory posting did not return base quantity."),
                    context.CorrelationId.Value))
            : Result<InventoryMovementReceipt>.Failure(
                InventoryMasterCommandHandler.MapError(result.Outcome, "inventory.movement"));
    }

    public Task<Result<InventoryReservationReceipt>> CreateAsync(
        CreateReservationCommand command,
        IExecutionContext context,
        CancellationToken cancellationToken) =>
        CreateReservationCoreAsync(command, context, cancellationToken);

    public Task<Result<InventoryReservationReceipt>> IncreaseAsync(
        ChangeReservationCommand command,
        IExecutionContext context,
        CancellationToken cancellationToken) =>
        ChangeReservationCoreAsync(
            command,
            ReservationMovementKind.Increase,
            "inventory.reservation.increase",
            "InventoryReservationIncreased",
            context,
            cancellationToken);

    public Task<Result<InventoryReservationReceipt>> ReleaseAsync(
        ChangeReservationCommand command,
        IExecutionContext context,
        CancellationToken cancellationToken) =>
        ChangeReservationCoreAsync(
            command,
            ReservationMovementKind.Release,
            "inventory.reservation.release",
            "InventoryReservationReleased",
            context,
            cancellationToken);

    public Task<Result<InventoryReservationReceipt>> ConsumeAsync(
        ChangeReservationCommand command,
        IExecutionContext context,
        CancellationToken cancellationToken) =>
        ChangeReservationCoreAsync(
            command,
            ReservationMovementKind.Consume,
            "inventory.reservation.consume",
            "InventoryReservationConsumed",
            context,
            cancellationToken);

    private async Task<Result<InventoryReservationReceipt>> CreateReservationCoreAsync(
        CreateReservationCommand command,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(command);
        ArgumentNullException.ThrowIfNull(context);

        if (command.SalesOrderPublicId == Guid.Empty ||
            command.SalesOrderVersion <= 0 ||
            command.SalesOrderLinePublicId == Guid.Empty ||
            command.ProductPublicId == Guid.Empty ||
            command.VariantPublicId == Guid.Empty ||
            command.WarehousePublicId == Guid.Empty ||
            command.UomPublicId == Guid.Empty ||
            command.EnteredQuantity <= 0m ||
            command.ConversionFactorSnapshot <= 0m)
            return Result<InventoryReservationReceipt>.Failure(
                new ApplicationError(
                    ErrorCategory.Validation,
                    "inventory.reservation.invalid",
                    "Sales source, Product/UOM/Warehouse and positive quantity are required; optional Variant id cannot be empty."));

        var publicId = Guid.NewGuid();
        var writeContext = InventoryMasterCommandHandler.CreateContext(
            "inventory.reservation.create",
            command.OperationKey,
            "InventoryReservationCreated",
            "InventoryReservation",
            publicId,
            context);
        if (writeContext.Error is not null)
            return Result<InventoryReservationReceipt>.Failure(writeContext.Error);

        var result = await persistence.CreateReservationAsync(
            new CreateReservationWrite(
                publicId,
                command,
                writeContext.Context!),
            cancellationToken);

        return ReservationResult(result, publicId, context);
    }

    private async Task<Result<InventoryReservationReceipt>> ChangeReservationCoreAsync(
        ChangeReservationCommand command,
        ReservationMovementKind kind,
        string scope,
        string action,
        IExecutionContext context,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(command);
        ArgumentNullException.ThrowIfNull(context);

        if (command.ReservationPublicId == Guid.Empty ||
            command.EnteredQuantity <= 0m)
            return Result<InventoryReservationReceipt>.Failure(
                new ApplicationError(
                    ErrorCategory.Validation,
                    scope + ".invalid",
                    "Reservation id and positive quantity are required."));

        var writeContext = InventoryMasterCommandHandler.CreateContext(
            scope,
            command.OperationKey,
            action,
            "InventoryReservation",
            command.ReservationPublicId,
            context);
        if (writeContext.Error is not null)
            return Result<InventoryReservationReceipt>.Failure(writeContext.Error);

        var result = await persistence.ChangeReservationAsync(
            new ChangeReservationWrite(
                command.ReservationPublicId,
                kind,
                command,
                writeContext.Context!),
            cancellationToken);

        return ReservationResult(result, command.ReservationPublicId, context);
    }

    private static Result<InventoryReservationReceipt> ReservationResult(
        InventoryMutationPersistenceResult result,
        Guid fallbackPublicId,
        IExecutionContext context)
    {
        return result.Outcome == InventoryMutationOutcome.Succeeded
            ? Result<InventoryReservationReceipt>.Success(
                new InventoryReservationReceipt(
                    result.EntityPublicId ?? fallbackPublicId,
                    result.BaseQuantity ?? throw new InvalidOperationException("Inventory Reservation did not return current base quantity."),
                    context.CorrelationId.Value))
            : Result<InventoryReservationReceipt>.Failure(
                InventoryMasterCommandHandler.MapError(result.Outcome, "inventory.reservation"));
    }
}
