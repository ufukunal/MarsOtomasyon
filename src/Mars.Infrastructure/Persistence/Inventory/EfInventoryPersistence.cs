using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Inventory;
using Mars.Domain.Inventory;
using Mars.Domain.Products;
using Mars.Infrastructure.Persistence.Products;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;
using Npgsql;

namespace Mars.Infrastructure.Persistence.Inventory;

public sealed partial class EfInventoryPersistence :
    IInventoryReadPersistence,
    IInventoryMutationPersistence,
    IInventoryAuthorityPersistence
{
    private readonly MarsDbContext dbContext;
    private readonly IAuditWriter auditWriter;
    private readonly IIdempotencyStore idempotencyStore;

    public EfInventoryPersistence(
        MarsDbContext dbContext,
        IAuditWriter auditWriter,
        IIdempotencyStore idempotencyStore)
    {
        this.dbContext = dbContext;
        this.auditWriter = auditWriter;
        this.idempotencyStore = idempotencyStore;
    }

    public Task<InventoryMutationPersistenceResult> CreateWarehouseAsync(
        CreateWarehouseWrite write,
        CancellationToken cancellationToken) =>
        ExecuteAsync(
            write.Context,
            () =>
            {
                dbContext.Add(new WarehouseRecord
                {
                    PublicId = write.Warehouse.PublicId,
                    CompanyId = write.Warehouse.CompanyId,
                    Code = write.Warehouse.Code,
                    Name = write.Warehouse.Name,
                    State = write.Warehouse.State,
                    Version = write.Warehouse.Version,
                    CreatedAt = write.Warehouse.CreatedAt
                });

                return Task.FromResult(
                    Success(
                        write.Warehouse.PublicId,
                        StateCode(write.Warehouse.State),
                        write.Warehouse.Version));
            },
            "inventory.warehouse.create.completed",
            cancellationToken);

    public Task<InventoryMutationPersistenceResult> EditWarehouseAsync(
        EditWarehouseWrite write,
        CancellationToken cancellationToken) =>
        ExecuteAsync(
            write.Context,
            async () =>
            {
                var warehouse = await dbContext.Set<WarehouseRecord>()
                    .SingleOrDefaultAsync(
                        x => x.PublicId == write.WarehousePublicId &&
                             x.CompanyId == write.Context.CompanyId,
                        cancellationToken);
                if (warehouse is null)
                    return Outcome(InventoryMutationOutcome.NotFound);
                if (warehouse.Version != write.ExpectedVersion)
                    return Outcome(InventoryMutationOutcome.StaleVersion);

                warehouse.Code = write.Code;
                warehouse.Name = write.Name;
                warehouse.Version = checked(warehouse.Version + 1);

                return Success(
                    warehouse.PublicId,
                    StateCode(warehouse.State),
                    warehouse.Version);
            },
            "inventory.warehouse.edit.completed",
            cancellationToken);

    public Task<InventoryMutationPersistenceResult> ChangeWarehouseStateAsync(
        ChangeWarehouseStateWrite write,
        CancellationToken cancellationToken) =>
        ExecuteAsync(
            write.Context,
            async () =>
            {
                var warehouse = await FindWarehouseForUpdateAsync(
                    write.WarehousePublicId,
                    write.Context.CompanyId,
                    cancellationToken);
                if (warehouse is null)
                    return Outcome(InventoryMutationOutcome.NotFound);
                if (warehouse.Version != write.ExpectedVersion)
                    return Outcome(InventoryMutationOutcome.StaleVersion);
                if (warehouse.State == write.TargetState)
                    return Outcome(InventoryMutationOutcome.StateConflict);

                if (write.TargetState == InventoryMasterState.Inactive)
                {
                    if (await GetWarehouseOnHandAsync(
                            warehouse.Id,
                            write.Context.CompanyId,
                            cancellationToken) != 0m ||
                        await GetWarehouseReservedAsync(
                            warehouse.Id,
                            write.Context.CompanyId,
                            null,
                            null,
                            cancellationToken) != 0m)
                    {
                        return Outcome(InventoryMutationOutcome.BusinessConflict);
                    }
                }

                warehouse.State = write.TargetState;
                warehouse.Version = checked(warehouse.Version + 1);

                return Success(
                    warehouse.PublicId,
                    StateCode(warehouse.State),
                    warehouse.Version);
            },
            "inventory.warehouse.state.completed",
            cancellationToken);

    public Task<InventoryMutationPersistenceResult> CreateLocationAsync(
        CreateLocationWrite write,
        CancellationToken cancellationToken) =>
        ExecuteAsync(
            write.Context,
            async () =>
            {
                var warehouse = await FindWarehouseForUpdateAsync(
                    write.Location.WarehousePublicId,
                    write.Context.CompanyId,
                    cancellationToken);
                if (warehouse is null)
                    return Outcome(InventoryMutationOutcome.NotFound);
                if (warehouse.State != InventoryMasterState.Active)
                    return Outcome(InventoryMutationOutcome.BusinessConflict);

                long? parentId = null;
                if (write.Location.ParentLocationPublicId.HasValue)
                {
                    var parent = await dbContext.Set<LocationRecord>()
                        .SingleOrDefaultAsync(
                            x => x.PublicId == write.Location.ParentLocationPublicId.Value &&
                                 x.CompanyId == write.Context.CompanyId &&
                                 x.WarehouseId == warehouse.Id,
                            cancellationToken);
                    if (parent is null)
                        return Outcome(InventoryMutationOutcome.NotFound);
                    parentId = parent.Id;
                }

                dbContext.Add(new LocationRecord
                {
                    PublicId = write.Location.PublicId,
                    CompanyId = write.Location.CompanyId,
                    WarehouseId = warehouse.Id,
                    ParentLocationId = parentId,
                    Code = write.Location.Code,
                    Name = write.Location.Name,
                    StockBearing = write.Location.StockBearing,
                    State = write.Location.State,
                    Version = write.Location.Version,
                    CreatedAt = write.Location.CreatedAt
                });

                return Success(
                    write.Location.PublicId,
                    StateCode(write.Location.State),
                    write.Location.Version);
            },
            "inventory.location.create.completed",
            cancellationToken);

    public Task<InventoryMutationPersistenceResult> EditLocationAsync(
        EditLocationWrite write,
        CancellationToken cancellationToken) =>
        ExecuteAsync(
            write.Context,
            async () =>
            {
                var probe = await dbContext.Set<LocationRecord>()
                    .AsNoTracking()
                    .SingleOrDefaultAsync(
                        x => x.PublicId == write.LocationPublicId &&
                             x.CompanyId == write.Context.CompanyId,
                        cancellationToken);
                if (probe is null)
                    return Outcome(InventoryMutationOutcome.NotFound);

                await LockWarehouseAsync(
                    probe.WarehouseId,
                    write.Context.CompanyId,
                    cancellationToken);

                var location = await dbContext.Set<LocationRecord>()
                    .SingleAsync(
                        x => x.Id == probe.Id &&
                             x.CompanyId == write.Context.CompanyId,
                        cancellationToken);
                if (location.Version != write.ExpectedVersion)
                    return Outcome(InventoryMutationOutcome.StaleVersion);

                long? parentId = null;
                if (write.ParentLocationPublicId.HasValue)
                {
                    var parent = await dbContext.Set<LocationRecord>()
                        .AsNoTracking()
                        .SingleOrDefaultAsync(
                            x => x.PublicId == write.ParentLocationPublicId.Value &&
                                 x.CompanyId == write.Context.CompanyId &&
                                 x.WarehouseId == location.WarehouseId,
                            cancellationToken);
                    if (parent is null)
                        return Outcome(InventoryMutationOutcome.NotFound);
                    if (parent.Id == location.Id ||
                        await IsLocationDescendantAsync(
                            parent.Id,
                            location.Id,
                            location.WarehouseId,
                            write.Context.CompanyId,
                            cancellationToken))
                    {
                        return Outcome(InventoryMutationOutcome.BusinessConflict);
                    }
                    parentId = parent.Id;
                }

                if (!write.StockBearing && location.StockBearing &&
                    await GetLocationOnHandAsync(
                        location.Id,
                        write.Context.CompanyId,
                        cancellationToken) != 0m)
                {
                    return Outcome(InventoryMutationOutcome.BusinessConflict);
                }

                location.ParentLocationId = parentId;
                location.Code = write.Code;
                location.Name = write.Name;
                location.StockBearing = write.StockBearing;
                location.Version = checked(location.Version + 1);

                return Success(
                    location.PublicId,
                    StateCode(location.State),
                    location.Version);
            },
            "inventory.location.edit.completed",
            cancellationToken);

    public Task<InventoryMutationPersistenceResult> ChangeLocationStateAsync(
        ChangeLocationStateWrite write,
        CancellationToken cancellationToken) =>
        ExecuteAsync(
            write.Context,
            async () =>
            {
                var probe = await dbContext.Set<LocationRecord>()
                    .AsNoTracking()
                    .SingleOrDefaultAsync(
                        x => x.PublicId == write.LocationPublicId &&
                             x.CompanyId == write.Context.CompanyId,
                        cancellationToken);
                if (probe is null)
                    return Outcome(InventoryMutationOutcome.NotFound);

                var warehouse = await LockWarehouseAsync(
                    probe.WarehouseId,
                    write.Context.CompanyId,
                    cancellationToken);
                if (warehouse is null)
                    return Outcome(InventoryMutationOutcome.NotFound);

                var location = await dbContext.Set<LocationRecord>()
                    .SingleAsync(
                        x => x.Id == probe.Id &&
                             x.CompanyId == write.Context.CompanyId,
                        cancellationToken);

                if (location.Version != write.ExpectedVersion)
                    return Outcome(InventoryMutationOutcome.StaleVersion);
                if (location.State == write.TargetState)
                    return Outcome(InventoryMutationOutcome.StateConflict);

                if (write.TargetState == InventoryMasterState.Inactive &&
                    await GetLocationOnHandAsync(
                        location.Id,
                        write.Context.CompanyId,
                        cancellationToken) != 0m)
                {
                    return Outcome(InventoryMutationOutcome.BusinessConflict);
                }

                if (write.TargetState == InventoryMasterState.Active &&
                    warehouse.State != InventoryMasterState.Active)
                {
                    return Outcome(InventoryMutationOutcome.BusinessConflict);
                }

                location.State = write.TargetState;
                location.Version = checked(location.Version + 1);

                return Success(
                    location.PublicId,
                    StateCode(location.State),
                    location.Version);
            },
            "inventory.location.state.completed",
            cancellationToken);

    public Task<InventoryMutationPersistenceResult> CreateLotAsync(
        CreateLotWrite write,
        CancellationToken cancellationToken) =>
        ExecuteAsync(
            write.Context,
            async () =>
            {
                var trade = await ResolveTraceIdentityAsync(
                    write.Context.CompanyId,
                    write.Lot.ProductPublicId,
                    write.Lot.VariantPublicId,
                    requireActive: true,
                    cancellationToken);
                if (trade.Outcome != InventoryMutationOutcome.Succeeded)
                    return Outcome(trade.Outcome);
                if (trade.Value!.Tracking is not ProductTrackingStrategy.Lot and
                    not ProductTrackingStrategy.LotSerial)
                    return Outcome(InventoryMutationOutcome.TrackingConflict);

                dbContext.Add(new InventoryLotRecord
                {
                    PublicId = write.Lot.PublicId,
                    CompanyId = write.Lot.CompanyId,
                    ProductId = trade.Value.ProductId,
                    VariantId = trade.Value.VariantId,
                    Code = write.Lot.Code,
                    ManufactureDate = write.Lot.ManufactureDate,
                    ExpiryDate = write.Lot.ExpiryDate,
                    Version = write.Lot.Version,
                    CreatedAt = write.Lot.CreatedAt
                });

                return Success(write.Lot.PublicId, "ACTIVE", write.Lot.Version);
            },
            "inventory.lot.create.completed",
            cancellationToken);

    public Task<InventoryMutationPersistenceResult> UpdateLotAsync(
        UpdateLotWrite write,
        CancellationToken cancellationToken) =>
        ExecuteAsync(
            write.Context,
            async () =>
            {
                var lot = await dbContext.Set<InventoryLotRecord>()
                    .SingleOrDefaultAsync(
                        x => x.PublicId == write.LotPublicId &&
                             x.CompanyId == write.Context.CompanyId,
                        cancellationToken);
                if (lot is null)
                    return Outcome(InventoryMutationOutcome.NotFound);
                if (lot.Version != write.ExpectedVersion)
                    return Outcome(InventoryMutationOutcome.StaleVersion);
                if (write.ManufactureDate.HasValue &&
                    write.ExpiryDate.HasValue &&
                    write.ExpiryDate.Value < write.ManufactureDate.Value)
                    return Outcome(InventoryMutationOutcome.BusinessConflict);

                lot.ManufactureDate = write.ManufactureDate;
                lot.ExpiryDate = write.ExpiryDate;
                lot.Version = checked(lot.Version + 1);

                return Success(lot.PublicId, "ACTIVE", lot.Version);
            },
            "inventory.lot.edit.completed",
            cancellationToken);

    public Task<InventoryMutationPersistenceResult> CreateSerialAsync(
        CreateSerialWrite write,
        CancellationToken cancellationToken) =>
        ExecuteAsync(
            write.Context,
            async () =>
            {
                var trade = await ResolveTraceIdentityAsync(
                    write.Context.CompanyId,
                    write.Serial.ProductPublicId,
                    write.Serial.VariantPublicId,
                    requireActive: true,
                    cancellationToken);
                if (trade.Outcome != InventoryMutationOutcome.Succeeded)
                    return Outcome(trade.Outcome);
                if (trade.Value!.Tracking is not ProductTrackingStrategy.Serial and
                    not ProductTrackingStrategy.LotSerial)
                    return Outcome(InventoryMutationOutcome.TrackingConflict);

                long? lotId = null;
                if (write.Serial.LotPublicId.HasValue)
                {
                    var lot = await dbContext.Set<InventoryLotRecord>()
                        .SingleOrDefaultAsync(
                            x => x.PublicId == write.Serial.LotPublicId.Value &&
                                 x.CompanyId == write.Context.CompanyId &&
                                 x.ProductId == trade.Value.ProductId &&
                                 x.VariantId == trade.Value.VariantId,
                            cancellationToken);
                    if (lot is null)
                        return Outcome(InventoryMutationOutcome.TrackingConflict);
                    lotId = lot.Id;
                }

                if (trade.Value.Tracking == ProductTrackingStrategy.LotSerial && !lotId.HasValue)
                    return Outcome(InventoryMutationOutcome.TrackingConflict);
                if (trade.Value.Tracking == ProductTrackingStrategy.Serial && lotId.HasValue)
                    return Outcome(InventoryMutationOutcome.TrackingConflict);

                dbContext.Add(new InventorySerialRecord
                {
                    PublicId = write.Serial.PublicId,
                    CompanyId = write.Serial.CompanyId,
                    ProductId = trade.Value.ProductId,
                    VariantId = trade.Value.VariantId,
                    LotId = lotId,
                    Value = write.Serial.Value,
                    Version = write.Serial.Version,
                    CreatedAt = write.Serial.CreatedAt
                });

                return Success(write.Serial.PublicId, "ACTIVE", write.Serial.Version);
            },
            "inventory.serial.create.completed",
            cancellationToken);

    public Task<InventoryMutationPersistenceResult> PostMovementAsync(
        PostInventoryMovementWrite write,
        CancellationToken cancellationToken) =>
        ExecuteAsync(
            write.Context,
            async () =>
            {
                var trade = await ResolveTradeIdentityAsync(
                    write.Context.CompanyId,
                    write.Command.ProductPublicId,
                    write.Command.VariantPublicId,
                    write.Command.UomPublicId,
                    requireActive: false,
                    cancellationToken);
                if (trade.Outcome != InventoryMutationOutcome.Succeeded)
                    return Outcome(trade.Outcome);

                var baseQuantity = checked(
                    write.Command.EnteredQuantity *
                    write.Command.ConversionFactorSnapshot);

                var positions = await ResolvePositionsAsync(
                    write.Context.CompanyId,
                    trade.Value!,
                    write.Command.Source,
                    write.Command.Target,
                    write.Command.ReversalOfMovementPublicId.HasValue,
                    cancellationToken);
                if (positions.Outcome != InventoryMutationOutcome.Succeeded)
                    return Outcome(positions.Outcome);

                foreach (var warehouseId in new[]
                         {
                             positions.Source?.WarehouseId,
                             positions.Target?.WarehouseId
                         }
                         .Where(x => x.HasValue)
                         .Select(x => x!.Value)
                         .Distinct()
                         .OrderBy(x => x))
                {
                    if (await LockWarehouseAsync(
                            warehouseId,
                            write.Context.CompanyId,
                            cancellationToken) is null)
                    {
                        return Outcome(InventoryMutationOutcome.NotFound);
                    }
                }

                if (positions.SerialId.HasValue)
                {
                    var serial = await LockSerialAsync(
                        positions.SerialId.Value,
                        write.Context.CompanyId,
                        cancellationToken);
                    if (serial is null)
                        return Outcome(InventoryMutationOutcome.TrackingConflict);
                    if (baseQuantity != 1m)
                        return Outcome(InventoryMutationOutcome.TrackingConflict);

                    var serialOnHand = await GetSerialOnHandAsync(
                        serial.Id,
                        write.Context.CompanyId,
                        cancellationToken);
                    if (positions.Source is null)
                    {
                        if (serialOnHand != 0m)
                            return Outcome(InventoryMutationOutcome.TrackingConflict);
                    }
                    else
                    {
                        if (serialOnHand != 1m)
                            return Outcome(InventoryMutationOutcome.TrackingConflict);

                        var exactSerialSource = await GetPositionOnHandAsync(
                            write.Context.CompanyId,
                            trade.Value!.ProductId,
                            trade.Value.VariantId,
                            positions.Source,
                            positions.LotId,
                            positions.SerialId,
                            cancellationToken);
                        if (exactSerialSource != 1m)
                            return Outcome(InventoryMutationOutcome.TrackingConflict);
                    }
                }

                if (positions.Source is not null)
                {
                    var sourceOnHand = await GetPositionOnHandAsync(
                        write.Context.CompanyId,
                        trade.Value!.ProductId,
                        trade.Value.VariantId,
                        positions.Source,
                        positions.LotId,
                        positions.SerialId,
                        cancellationToken);
                    if (sourceOnHand < baseQuantity)
                        return Outcome(InventoryMutationOutcome.InsufficientStock);
                }

                long? reversalId = null;
                if (write.Command.ReversalOfMovementPublicId.HasValue)
                {
                    var original = await dbContext.Set<InventoryMovementRecord>()
                        .SingleOrDefaultAsync(
                            x => x.PublicId == write.Command.ReversalOfMovementPublicId.Value &&
                                 x.CompanyId == write.Context.CompanyId,
                            cancellationToken);
                    if (original is null)
                        return Outcome(InventoryMutationOutcome.NotFound);
                    if (!ExactReversal(
                            original,
                            trade.Value!,
                            write.Command,
                            positions,
                            baseQuantity))
                        return Outcome(InventoryMutationOutcome.BusinessConflict);
                    reversalId = original.Id;
                }

                dbContext.Add(new InventoryMovementRecord
                {
                    PublicId = write.PublicId,
                    CompanyId = write.Context.CompanyId,
                    ProductId = trade.Value!.ProductId,
                    VariantId = trade.Value.VariantId,
                    UomId = trade.Value.UomId,
                    EnteredQuantity = write.Command.EnteredQuantity,
                    ConversionFactorSnapshot = write.Command.ConversionFactorSnapshot,
                    BaseQuantity = baseQuantity,
                    SourceWarehouseId = positions.Source?.WarehouseId,
                    SourceLocationId = positions.Source?.LocationId,
                    SourceDispositionId = positions.Source?.DispositionId,
                    TargetWarehouseId = positions.Target?.WarehouseId,
                    TargetLocationId = positions.Target?.LocationId,
                    TargetDispositionId = positions.Target?.DispositionId,
                    LotId = positions.LotId,
                    SerialId = positions.SerialId,
                    SourceModule = write.Command.SourceIdentity.Module,
                    SourceEntityType = write.Command.SourceIdentity.EntityType,
                    SourceDocumentPublicId = write.Command.SourceIdentity.DocumentPublicId,
                    SourceLinePublicId = write.Command.SourceIdentity.LinePublicId,
                    PostedAt = write.Context.Audit.OccurredAt,
                    ActorId = write.Context.Audit.ActorId,
                    CorrelationId = write.Context.Audit.CorrelationId,
                    ReversalOfMovementId = reversalId
                });

                return new InventoryMutationPersistenceResult(
                    InventoryMutationOutcome.Succeeded,
                    write.PublicId,
                    "POSTED",
                    1,
                    baseQuantity);
            },
            "inventory.movement.post.completed",
            cancellationToken);

    public Task<InventoryMutationPersistenceResult> CreateReservationAsync(
        CreateReservationWrite write,
        CancellationToken cancellationToken) =>
        ExecuteAsync(
            write.Context,
            async () =>
            {
                var trade = await ResolveTradeIdentityAsync(
                    write.Context.CompanyId,
                    write.Command.ProductPublicId,
                    write.Command.VariantPublicId,
                    write.Command.UomPublicId,
                    requireActive: true,
                    cancellationToken);
                if (trade.Outcome != InventoryMutationOutcome.Succeeded)
                    return Outcome(trade.Outcome);

                var warehouse = await FindWarehouseForUpdateAsync(
                    write.Command.WarehousePublicId,
                    write.Context.CompanyId,
                    cancellationToken);
                if (warehouse is null)
                    return Outcome(InventoryMutationOutcome.NotFound);
                if (warehouse.State != InventoryMasterState.Active)
                    return Outcome(InventoryMutationOutcome.BusinessConflict);

                var baseQuantity = checked(
                    write.Command.EnteredQuantity *
                    write.Command.ConversionFactorSnapshot);

                var available = await GetAvailableOnHandAsync(
                    write.Context.CompanyId,
                    trade.Value!.ProductId,
                    trade.Value.VariantId,
                    warehouse.Id,
                    cancellationToken);
                var reserved = await GetWarehouseReservedAsync(
                    warehouse.Id,
                    write.Context.CompanyId,
                    trade.Value.ProductId,
                    trade.Value.VariantId,
                    cancellationToken);
                if (available - reserved < baseQuantity)
                    return Outcome(InventoryMutationOutcome.BusinessConflict);

                var reservation = new InventoryReservationRecord
                {
                    PublicId = write.PublicId,
                    CompanyId = write.Context.CompanyId,
                    SalesOrderPublicId = write.Command.SalesOrderPublicId,
                    SalesOrderVersion = write.Command.SalesOrderVersion,
                    SalesOrderLinePublicId = write.Command.SalesOrderLinePublicId,
                    ProductId = trade.Value.ProductId,
                    VariantId = trade.Value.VariantId,
                    WarehouseId = warehouse.Id,
                    UomId = trade.Value.UomId,
                    ConversionFactorSnapshot = write.Command.ConversionFactorSnapshot,
                    CreatedAt = write.Context.Audit.OccurredAt
                };
                dbContext.Add(reservation);
                await dbContext.SaveChangesAsync(cancellationToken);

                dbContext.Add(new InventoryReservationMovementRecord
                {
                    PublicId = Guid.NewGuid(),
                    ReservationId = reservation.Id,
                    CompanyId = reservation.CompanyId,
                    Kind = ReservationMovementKind.Create,
                    EnteredQuantity = write.Command.EnteredQuantity,
                    BaseQuantity = baseQuantity,
                    SourceModule = "Sales",
                    SourceDocumentPublicId = write.Command.SalesOrderPublicId,
                    SourceLinePublicId = write.Command.SalesOrderLinePublicId,
                    ActorId = write.Context.Audit.ActorId,
                    CorrelationId = write.Context.Audit.CorrelationId,
                    OccurredAt = write.Context.Audit.OccurredAt
                });

                return new InventoryMutationPersistenceResult(
                    InventoryMutationOutcome.Succeeded,
                    reservation.PublicId,
                    "ACTIVE",
                    1,
                    baseQuantity);
            },
            "inventory.reservation.create.completed",
            cancellationToken);

    public Task<InventoryMutationPersistenceResult> ChangeReservationAsync(
        ChangeReservationWrite write,
        CancellationToken cancellationToken) =>
        ExecuteAsync(
            write.Context,
            async () =>
            {
                var probe = await dbContext.Set<InventoryReservationRecord>()
                    .AsNoTracking()
                    .SingleOrDefaultAsync(
                        x => x.PublicId == write.ReservationPublicId &&
                             x.CompanyId == write.Context.CompanyId,
                        cancellationToken);
                if (probe is null)
                    return Outcome(InventoryMutationOutcome.NotFound);

                if (await LockWarehouseAsync(
                        probe.WarehouseId,
                        write.Context.CompanyId,
                        cancellationToken) is null)
                    return Outcome(InventoryMutationOutcome.NotFound);

                var reservation = await LockReservationAsync(
                    probe.Id,
                    write.Context.CompanyId,
                    cancellationToken);
                if (reservation is null)
                    return Outcome(InventoryMutationOutcome.NotFound);

                var deltaBase = checked(
                    write.Command.EnteredQuantity *
                    reservation.ConversionFactorSnapshot);
                var current = await GetReservationCurrentAsync(
                    reservation.Id,
                    reservation.CompanyId,
                    cancellationToken);

                if (write.Kind is ReservationMovementKind.Release or ReservationMovementKind.Consume)
                {
                    if (current < deltaBase)
                        return Outcome(InventoryMutationOutcome.BusinessConflict);
                }
                else if (write.Kind == ReservationMovementKind.Increase)
                {
                    var available = await GetAvailableOnHandAsync(
                        reservation.CompanyId,
                        reservation.ProductId,
                        reservation.VariantId,
                        reservation.WarehouseId,
                        cancellationToken);
                    var reserved = await GetWarehouseReservedAsync(
                        reservation.WarehouseId,
                        reservation.CompanyId,
                        reservation.ProductId,
                        reservation.VariantId,
                        cancellationToken);
                    if (available - reserved < deltaBase)
                        return Outcome(InventoryMutationOutcome.BusinessConflict);
                }

                dbContext.Add(new InventoryReservationMovementRecord
                {
                    PublicId = Guid.NewGuid(),
                    ReservationId = reservation.Id,
                    CompanyId = reservation.CompanyId,
                    Kind = write.Kind,
                    EnteredQuantity = write.Command.EnteredQuantity,
                    BaseQuantity = deltaBase,
                    SourceModule = write.Command.SourceIdentity?.Module,
                    SourceDocumentPublicId = write.Command.SourceIdentity?.DocumentPublicId,
                    SourceLinePublicId = write.Command.SourceIdentity?.LinePublicId,
                    ActorId = write.Context.Audit.ActorId,
                    CorrelationId = write.Context.Audit.CorrelationId,
                    OccurredAt = write.Context.Audit.OccurredAt
                });

                var next = write.Kind is ReservationMovementKind.Create or ReservationMovementKind.Increase
                    ? current + deltaBase
                    : current - deltaBase;

                return new InventoryMutationPersistenceResult(
                    InventoryMutationOutcome.Succeeded,
                    reservation.PublicId,
                    next == 0m ? "CLOSED" : "ACTIVE",
                    1,
                    next);
            },
            "inventory.reservation.change.completed",
            cancellationToken);

    private async Task<InventoryMutationPersistenceResult> ExecuteAsync(
        InventoryWriteContext context,
        Func<Task<InventoryMutationPersistenceResult>> mutation,
        string resultCode,
        CancellationToken cancellationToken)
    {
        var ownsTransaction = dbContext.Database.CurrentTransaction is null;
        IDbContextTransaction? transaction = null;
        if (ownsTransaction)
            transaction = await dbContext.Database.BeginTransactionAsync(cancellationToken);

        try
        {
            var result = await mutation();
            if (result.Outcome != InventoryMutationOutcome.Succeeded)
            {
                if (transaction is not null)
                    await transaction.RollbackAsync(cancellationToken);
                dbContext.ChangeTracker.Clear();
                return result;
            }

            idempotencyStore.Add(context.Idempotency);
            auditWriter.Append(context.Audit);
            await dbContext.SaveChangesAsync(cancellationToken);

            var marked = await idempotencyStore.MarkSucceededAsync(
                context.Idempotency.Scope,
                context.Idempotency.OperationKey,
                resultCode,
                context.Audit.OccurredAt,
                cancellationToken);
            if (!marked)
                throw new InvalidOperationException(
                    "Inventory idempotency state could not be completed.");

            if (transaction is not null)
                await transaction.CommitAsync(cancellationToken);
            return result;
        }
        catch (DbUpdateConcurrencyException)
        {
            if (transaction is not null)
                await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return Outcome(InventoryMutationOutcome.StaleVersion);
        }
        catch (DbUpdateException exception) when (
            IsConstraint(exception, "ux_idempotency_scope_key"))
        {
            if (transaction is not null)
                await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return Outcome(InventoryMutationOutcome.DuplicateOperation);
        }
        catch (DbUpdateException exception) when (IsUniqueViolation(exception))
        {
            if (transaction is not null)
                await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return Outcome(InventoryMutationOutcome.DeterministicConflict);
        }
        catch (DbUpdateException exception) when (IsBusinessConstraint(exception))
        {
            if (transaction is not null)
                await transaction.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            return Outcome(InventoryMutationOutcome.BusinessConflict);
        }
        finally
        {
            if (transaction is not null)
                await transaction.DisposeAsync();
        }
    }

    private async Task<WarehouseRecord?> FindWarehouseForUpdateAsync(
        Guid publicId,
        Guid companyId,
        CancellationToken cancellationToken) =>
        await dbContext.Set<WarehouseRecord>()
            .FromSqlInterpolated(
                $"SELECT * FROM inventory.warehouses WHERE public_id = {publicId} AND company_id = {companyId} FOR UPDATE")
            .SingleOrDefaultAsync(cancellationToken);

    private async Task<WarehouseRecord?> LockWarehouseAsync(
        long id,
        Guid companyId,
        CancellationToken cancellationToken) =>
        await dbContext.Set<WarehouseRecord>()
            .FromSqlInterpolated(
                $"SELECT * FROM inventory.warehouses WHERE id = {id} AND company_id = {companyId} FOR UPDATE")
            .SingleOrDefaultAsync(cancellationToken);

    private async Task<InventorySerialRecord?> LockSerialAsync(
        long id,
        Guid companyId,
        CancellationToken cancellationToken) =>
        await dbContext.Set<InventorySerialRecord>()
            .FromSqlInterpolated(
                $"SELECT * FROM inventory.serials WHERE id = {id} AND company_id = {companyId} FOR UPDATE")
            .SingleOrDefaultAsync(cancellationToken);

    private async Task<InventoryReservationRecord?> LockReservationAsync(
        long id,
        Guid companyId,
        CancellationToken cancellationToken) =>
        await dbContext.Set<InventoryReservationRecord>()
            .FromSqlInterpolated(
                $"SELECT * FROM inventory.reservations WHERE id = {id} AND company_id = {companyId} FOR UPDATE")
            .SingleOrDefaultAsync(cancellationToken);

    private async Task<(InventoryMutationOutcome Outcome, ResolvedTradeIdentity? Value)> ResolveTraceIdentityAsync(
        Guid companyId,
        Guid productPublicId,
        Guid? variantPublicId,
        bool requireActive,
        CancellationToken cancellationToken)
    {
        var product = await dbContext.Set<ProductRecord>()
            .AsNoTracking()
            .SingleOrDefaultAsync(
                x => x.PublicId == productPublicId &&
                     x.CompanyId == companyId,
                cancellationToken);
        if (product is null)
            return (InventoryMutationOutcome.NotFound, null);
        if (!product.Stockable)
            return (InventoryMutationOutcome.BusinessConflict, null);
        if (requireActive && product.State != ProductState.Active)
            return (InventoryMutationOutcome.BusinessConflict, null);

        ProductVariantRecord? variant = null;
        if (variantPublicId.HasValue)
        {
            variant = await dbContext.Set<ProductVariantRecord>()
                .AsNoTracking()
                .SingleOrDefaultAsync(
                    x => x.PublicId == variantPublicId.Value &&
                         x.CompanyId == companyId &&
                         x.ProductId == product.Id,
                    cancellationToken);
            if (variant is null)
                return (InventoryMutationOutcome.NotFound, null);
            if (requireActive &&
                variant.State != ProductMasterRecordState.Active)
                return (InventoryMutationOutcome.BusinessConflict, null);
        }

        return (
            InventoryMutationOutcome.Succeeded,
            new ResolvedTradeIdentity(
                product.Id,
                variant?.Id,
                0,
                variant?.TrackingStrategy ?? product.TrackingStrategy));
    }

    private async Task<(InventoryMutationOutcome Outcome, ResolvedTradeIdentity? Value)> ResolveTradeIdentityAsync(
        Guid companyId,
        Guid productPublicId,
        Guid? variantPublicId,
        Guid uomPublicId,
        bool requireActive,
        CancellationToken cancellationToken)
    {
        var trace = await ResolveTraceIdentityAsync(
            companyId,
            productPublicId,
            variantPublicId,
            requireActive,
            cancellationToken);
        if (trace.Outcome != InventoryMutationOutcome.Succeeded)
            return trace;

        var uom = await dbContext.Set<UnitOfMeasureRecord>()
            .AsNoTracking()
            .SingleOrDefaultAsync(
                x => x.PublicId == uomPublicId &&
                     x.CompanyId == companyId &&
                     (!requireActive || x.State == ProductMasterRecordState.Active),
                cancellationToken);
        if (uom is null)
            return (InventoryMutationOutcome.NotFound, null);

        var relationExists = await dbContext.Set<ProductUomRecord>()
            .AsNoTracking()
            .AnyAsync(
                x => x.ProductId == trace.Value!.ProductId &&
                     x.UomId == uom.Id &&
                     x.CompanyId == companyId &&
                     (x.VariantId == null || x.VariantId == trace.Value.VariantId) &&
                     (!requireActive || x.State == ProductMasterRecordState.Active),
                cancellationToken);
        if (!relationExists)
            return (InventoryMutationOutcome.BusinessConflict, null);

        return (
            InventoryMutationOutcome.Succeeded,
            trace.Value! with { UomId = uom.Id });
    }

    private async Task<(InventoryMutationOutcome Outcome, ResolvedPositions? Value)> ResolvePositionsAsync(
        Guid companyId,
        ResolvedTradeIdentity trade,
        InventoryPosition? source,
        InventoryPosition? target,
        bool isReversal,
        CancellationToken cancellationToken)
    {
        if (source is null && target is null)
            return (InventoryMutationOutcome.BusinessConflict, null);

        Guid? lotPublicId = source?.LotPublicId ?? target?.LotPublicId;
        Guid? serialPublicId = source?.SerialPublicId ?? target?.SerialPublicId;

        if (source is not null &&
            target is not null &&
            (source.LotPublicId != target.LotPublicId ||
             source.SerialPublicId != target.SerialPublicId))
            return (InventoryMutationOutcome.TrackingConflict, null);

        if (!TrackingMatches(trade.Tracking, lotPublicId, serialPublicId))
            return (InventoryMutationOutcome.TrackingConflict, null);

        long? lotId = null;
        if (lotPublicId.HasValue)
        {
            var lot = await dbContext.Set<InventoryLotRecord>()
                .AsNoTracking()
                .SingleOrDefaultAsync(
                    x => x.PublicId == lotPublicId.Value &&
                         x.CompanyId == companyId &&
                         x.ProductId == trade.ProductId &&
                         x.VariantId == trade.VariantId,
                    cancellationToken);
            if (lot is null)
                return (InventoryMutationOutcome.TrackingConflict, null);
            lotId = lot.Id;
        }

        long? serialId = null;
        if (serialPublicId.HasValue)
        {
            var serial = await dbContext.Set<InventorySerialRecord>()
                .AsNoTracking()
                .SingleOrDefaultAsync(
                    x => x.PublicId == serialPublicId.Value &&
                         x.CompanyId == companyId &&
                         x.ProductId == trade.ProductId &&
                         x.VariantId == trade.VariantId,
                    cancellationToken);
            if (serial is null)
                return (InventoryMutationOutcome.TrackingConflict, null);
            if (serial.LotId != lotId)
                return (InventoryMutationOutcome.TrackingConflict, null);
            serialId = serial.Id;
        }

        var sourceResolved = source is null
            ? (InventoryMutationOutcome.Succeeded, (ResolvedPosition?)null)
            : await ResolvePositionSideAsync(
                companyId,
                source,
                targetSide: false,
                allowInactive: true,
                cancellationToken);
        if (sourceResolved.Item1 != InventoryMutationOutcome.Succeeded)
            return (sourceResolved.Item1, null);

        var targetResolved = target is null
            ? (InventoryMutationOutcome.Succeeded, (ResolvedPosition?)null)
            : await ResolvePositionSideAsync(
                companyId,
                target,
                targetSide: true,
                allowInactive: isReversal,
                cancellationToken);
        if (targetResolved.Item1 != InventoryMutationOutcome.Succeeded)
            return (targetResolved.Item1, null);

        return (
            InventoryMutationOutcome.Succeeded,
            new ResolvedPositions(
                sourceResolved.Item2,
                targetResolved.Item2,
                lotId,
                serialId));
    }

    private async Task<(InventoryMutationOutcome, ResolvedPosition?)> ResolvePositionSideAsync(
        Guid companyId,
        InventoryPosition position,
        bool targetSide,
        bool allowInactive,
        CancellationToken cancellationToken)
    {
        var warehouse = await dbContext.Set<WarehouseRecord>()
            .AsNoTracking()
            .SingleOrDefaultAsync(
                x => x.PublicId == position.WarehousePublicId &&
                     x.CompanyId == companyId,
                cancellationToken);
        if (warehouse is null)
            return (InventoryMutationOutcome.NotFound, null);
        if (!allowInactive && warehouse.State != InventoryMasterState.Active)
            return (InventoryMutationOutcome.BusinessConflict, null);

        long? locationId = null;
        if (position.LocationPublicId.HasValue)
        {
            var location = await dbContext.Set<LocationRecord>()
                .AsNoTracking()
                .SingleOrDefaultAsync(
                    x => x.PublicId == position.LocationPublicId.Value &&
                         x.CompanyId == companyId &&
                         x.WarehouseId == warehouse.Id,
                    cancellationToken);
            if (location is null)
                return (InventoryMutationOutcome.NotFound, null);
            if (!location.StockBearing)
                return (InventoryMutationOutcome.BusinessConflict, null);
            if (!allowInactive && location.State != InventoryMasterState.Active)
                return (InventoryMutationOutcome.BusinessConflict, null);
            locationId = location.Id;
        }

        var dispositionId = (long)position.Disposition;
        var dispositionExists = await dbContext.Set<InventoryDispositionRecord>()
            .AsNoTracking()
            .AnyAsync(x => x.Id == dispositionId, cancellationToken);
        if (!dispositionExists)
            return (InventoryMutationOutcome.BusinessConflict, null);

        return (
            InventoryMutationOutcome.Succeeded,
            new ResolvedPosition(
                warehouse.Id,
                locationId,
                dispositionId));
    }

    private async Task<decimal> GetPositionOnHandAsync(
        Guid companyId,
        long productId,
        long? variantId,
        ResolvedPosition position,
        long? lotId,
        long? serialId,
        CancellationToken cancellationToken)
    {
        var incoming = await dbContext.Set<InventoryMovementRecord>()
            .Where(x =>
                x.CompanyId == companyId &&
                x.ProductId == productId &&
                x.VariantId == variantId &&
                x.TargetWarehouseId == position.WarehouseId &&
                x.TargetLocationId == position.LocationId &&
                x.TargetDispositionId == position.DispositionId &&
                x.LotId == lotId &&
                x.SerialId == serialId)
            .SumAsync(x => (decimal?)x.BaseQuantity, cancellationToken) ?? 0m;

        var outgoing = await dbContext.Set<InventoryMovementRecord>()
            .Where(x =>
                x.CompanyId == companyId &&
                x.ProductId == productId &&
                x.VariantId == variantId &&
                x.SourceWarehouseId == position.WarehouseId &&
                x.SourceLocationId == position.LocationId &&
                x.SourceDispositionId == position.DispositionId &&
                x.LotId == lotId &&
                x.SerialId == serialId)
            .SumAsync(x => (decimal?)x.BaseQuantity, cancellationToken) ?? 0m;

        return incoming - outgoing;
    }

    private async Task<decimal> GetSerialOnHandAsync(
        long serialId,
        Guid companyId,
        CancellationToken cancellationToken)
    {
        var incoming = await dbContext.Set<InventoryMovementRecord>()
            .Where(x =>
                x.CompanyId == companyId &&
                x.SerialId == serialId &&
                x.TargetWarehouseId != null)
            .SumAsync(x => (decimal?)x.BaseQuantity, cancellationToken) ?? 0m;

        var outgoing = await dbContext.Set<InventoryMovementRecord>()
            .Where(x =>
                x.CompanyId == companyId &&
                x.SerialId == serialId &&
                x.SourceWarehouseId != null)
            .SumAsync(x => (decimal?)x.BaseQuantity, cancellationToken) ?? 0m;

        return incoming - outgoing;
    }

    private async Task<decimal> GetWarehouseOnHandAsync(
        long warehouseId,
        Guid companyId,
        CancellationToken cancellationToken)
    {
        var incoming = await dbContext.Set<InventoryMovementRecord>()
            .Where(x =>
                x.CompanyId == companyId &&
                x.TargetWarehouseId == warehouseId)
            .SumAsync(x => (decimal?)x.BaseQuantity, cancellationToken) ?? 0m;

        var outgoing = await dbContext.Set<InventoryMovementRecord>()
            .Where(x =>
                x.CompanyId == companyId &&
                x.SourceWarehouseId == warehouseId)
            .SumAsync(x => (decimal?)x.BaseQuantity, cancellationToken) ?? 0m;

        return incoming - outgoing;
    }

    private async Task<decimal> GetLocationOnHandAsync(
        long locationId,
        Guid companyId,
        CancellationToken cancellationToken)
    {
        var incoming = await dbContext.Set<InventoryMovementRecord>()
            .Where(x =>
                x.CompanyId == companyId &&
                x.TargetLocationId == locationId)
            .SumAsync(x => (decimal?)x.BaseQuantity, cancellationToken) ?? 0m;

        var outgoing = await dbContext.Set<InventoryMovementRecord>()
            .Where(x =>
                x.CompanyId == companyId &&
                x.SourceLocationId == locationId)
            .SumAsync(x => (decimal?)x.BaseQuantity, cancellationToken) ?? 0m;

        return incoming - outgoing;
    }

    private async Task<decimal> GetAvailableOnHandAsync(
        Guid companyId,
        long productId,
        long? variantId,
        long warehouseId,
        CancellationToken cancellationToken)
    {
        var dispositionId = (long)InventoryDispositionCode.Available;

        var incoming = await dbContext.Set<InventoryMovementRecord>()
            .Where(x =>
                x.CompanyId == companyId &&
                x.ProductId == productId &&
                x.VariantId == variantId &&
                x.TargetWarehouseId == warehouseId &&
                x.TargetDispositionId == dispositionId)
            .SumAsync(x => (decimal?)x.BaseQuantity, cancellationToken) ?? 0m;

        var outgoing = await dbContext.Set<InventoryMovementRecord>()
            .Where(x =>
                x.CompanyId == companyId &&
                x.ProductId == productId &&
                x.VariantId == variantId &&
                x.SourceWarehouseId == warehouseId &&
                x.SourceDispositionId == dispositionId)
            .SumAsync(x => (decimal?)x.BaseQuantity, cancellationToken) ?? 0m;

        return incoming - outgoing;
    }

    private async Task<decimal> GetWarehouseReservedAsync(
        long warehouseId,
        Guid companyId,
        long? productId,
        long? variantId,
        CancellationToken cancellationToken)
    {
        var query =
            from movement in dbContext.Set<InventoryReservationMovementRecord>()
            join reservation in dbContext.Set<InventoryReservationRecord>()
                on new { movement.ReservationId, movement.CompanyId }
                equals new { ReservationId = reservation.Id, reservation.CompanyId }
            where reservation.CompanyId == companyId &&
                  reservation.WarehouseId == warehouseId &&
                  (!productId.HasValue || reservation.ProductId == productId.Value) &&
                  (!productId.HasValue || reservation.VariantId == variantId)
            select movement.Kind == ReservationMovementKind.Create ||
                   movement.Kind == ReservationMovementKind.Increase
                ? movement.BaseQuantity
                : -movement.BaseQuantity;

        return await query.SumAsync(x => (decimal?)x, cancellationToken) ?? 0m;
    }

    private Task<decimal> GetReservationCurrentAsync(
        long reservationId,
        Guid companyId,
        CancellationToken cancellationToken) =>
        dbContext.Set<InventoryReservationMovementRecord>()
            .Where(x =>
                x.ReservationId == reservationId &&
                x.CompanyId == companyId)
            .SumAsync(
                x => (decimal?)(x.Kind == ReservationMovementKind.Create ||
                                x.Kind == ReservationMovementKind.Increase
                    ? x.BaseQuantity
                    : -x.BaseQuantity),
                cancellationToken)
            .ContinueWith(
                task => task.Result ?? 0m,
                cancellationToken,
                TaskContinuationOptions.ExecuteSynchronously,
                TaskScheduler.Default);

    private async Task<bool> IsLocationDescendantAsync(
        long candidateParentId,
        long locationId,
        long warehouseId,
        Guid companyId,
        CancellationToken cancellationToken)
    {
        var current = candidateParentId;
        for (var depth = 0; depth < 128; depth++)
        {
            if (current == locationId)
                return true;

            var parent = await dbContext.Set<LocationRecord>()
                .AsNoTracking()
                .Where(x =>
                    x.Id == current &&
                    x.WarehouseId == warehouseId &&
                    x.CompanyId == companyId)
                .Select(x => x.ParentLocationId)
                .SingleOrDefaultAsync(cancellationToken);
            if (!parent.HasValue)
                return false;
            current = parent.Value;
        }

        return true;
    }

    private static bool TrackingMatches(
        ProductTrackingStrategy tracking,
        Guid? lotPublicId,
        Guid? serialPublicId) => tracking switch
        {
            ProductTrackingStrategy.None =>
                !lotPublicId.HasValue && !serialPublicId.HasValue,
            ProductTrackingStrategy.Lot =>
                lotPublicId.HasValue && !serialPublicId.HasValue,
            ProductTrackingStrategy.Serial =>
                !lotPublicId.HasValue && serialPublicId.HasValue,
            ProductTrackingStrategy.LotSerial =>
                lotPublicId.HasValue && serialPublicId.HasValue,
            _ => false
        };

    private static bool ExactReversal(
        InventoryMovementRecord original,
        ResolvedTradeIdentity trade,
        InventoryMovementCommand command,
        ResolvedPositions positions,
        decimal baseQuantity)
    {
        return original.ProductId == trade.ProductId &&
               original.VariantId == trade.VariantId &&
               original.UomId == trade.UomId &&
               original.EnteredQuantity == command.EnteredQuantity &&
               original.ConversionFactorSnapshot == command.ConversionFactorSnapshot &&
               original.BaseQuantity == baseQuantity &&
               original.LotId == positions.LotId &&
               original.SerialId == positions.SerialId &&
               original.SourceWarehouseId == positions.Target?.WarehouseId &&
               original.SourceLocationId == positions.Target?.LocationId &&
               original.SourceDispositionId == positions.Target?.DispositionId &&
               original.TargetWarehouseId == positions.Source?.WarehouseId &&
               original.TargetLocationId == positions.Source?.LocationId &&
               original.TargetDispositionId == positions.Source?.DispositionId;
    }

    private static InventoryMutationPersistenceResult Success(
        Guid publicId,
        string state,
        long version) =>
        new(
            InventoryMutationOutcome.Succeeded,
            publicId,
            state,
            version);

    private static InventoryMutationPersistenceResult Outcome(
        InventoryMutationOutcome outcome) =>
        new(outcome);

    private static string StateCode(InventoryMasterState state) =>
        state == InventoryMasterState.Active ? "ACTIVE" : "INACTIVE";

    private static bool IsConstraint(
        DbUpdateException exception,
        string constraintName) =>
        exception.InnerException is PostgresException postgres &&
        postgres.SqlState == PostgresErrorCodes.UniqueViolation &&
        string.Equals(
            postgres.ConstraintName,
            constraintName,
            StringComparison.Ordinal);

    private static bool IsUniqueViolation(DbUpdateException exception) =>
        exception.InnerException is PostgresException postgres &&
        postgres.SqlState == PostgresErrorCodes.UniqueViolation;

    private static bool IsBusinessConstraint(DbUpdateException exception) =>
        exception.InnerException is PostgresException postgres &&
        (postgres.SqlState == PostgresErrorCodes.ForeignKeyViolation ||
         postgres.SqlState == PostgresErrorCodes.CheckViolation);

    private sealed record ResolvedTradeIdentity(
        long ProductId,
        long? VariantId,
        long UomId,
        ProductTrackingStrategy Tracking);

    private sealed record ResolvedPosition(
        long WarehouseId,
        long? LocationId,
        long DispositionId);

    private sealed record ResolvedPositions(
        ResolvedPosition? Source,
        ResolvedPosition? Target,
        long? LotId,
        long? SerialId);
}
