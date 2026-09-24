using Mars.Application.Inventory;
using Mars.Domain.Inventory;
using Mars.Infrastructure.Persistence.Products;
using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence.Inventory;

public sealed partial class EfInventoryPersistence
{
    public async Task<IReadOnlyList<WarehouseView>> ListWarehousesAsync(
        Guid companyId,
        string? search,
        CancellationToken cancellationToken)
    {
        var query = dbContext.Set<WarehouseRecord>()
            .AsNoTracking()
            .Where(x => x.CompanyId == companyId);

        if (!string.IsNullOrWhiteSpace(search))
        {
            var term = search.Trim();
            query = query.Where(x => x.Code.Contains(term) || x.Name.Contains(term));
        }

        var rows = await query
            .OrderBy(x => x.Code)
            .Take(250)
            .ToArrayAsync(cancellationToken);

        return rows.Select(x => new WarehouseView(
            x.PublicId,
            x.Code,
            x.Name,
            StateCode(x.State),
            x.Version)).ToArray();
    }

    public async Task<IReadOnlyList<LocationView>> ListLocationsAsync(
        Guid companyId,
        Guid? warehousePublicId,
        CancellationToken cancellationToken)
    {
        long? warehouseId = null;
        if (warehousePublicId.HasValue)
        {
            warehouseId = await dbContext.Set<WarehouseRecord>()
                .AsNoTracking()
                .Where(x => x.CompanyId == companyId && x.PublicId == warehousePublicId.Value)
                .Select(x => (long?)x.Id)
                .SingleOrDefaultAsync(cancellationToken);
            if (!warehouseId.HasValue)
                return Array.Empty<LocationView>();
        }

        var rows = await dbContext.Set<LocationRecord>()
            .AsNoTracking()
            .Where(x => x.CompanyId == companyId &&
                        (!warehouseId.HasValue || x.WarehouseId == warehouseId.Value))
            .OrderBy(x => x.WarehouseId)
            .ThenBy(x => x.Code)
            .Take(500)
            .ToArrayAsync(cancellationToken);

        if (rows.Length == 0)
            return Array.Empty<LocationView>();

        var warehouseIds = rows.Select(x => x.WarehouseId).Distinct().ToArray();
        var warehousePublicIds = await dbContext.Set<WarehouseRecord>()
            .AsNoTracking()
            .Where(x => x.CompanyId == companyId && warehouseIds.Contains(x.Id))
            .ToDictionaryAsync(x => x.Id, x => x.PublicId, cancellationToken);

        var parentIds = rows
            .Where(x => x.ParentLocationId.HasValue)
            .Select(x => x.ParentLocationId!.Value)
            .Distinct()
            .ToArray();
        var parentPublicIds = parentIds.Length == 0
            ? new Dictionary<long, Guid>()
            : await dbContext.Set<LocationRecord>()
                .AsNoTracking()
                .Where(x => x.CompanyId == companyId && parentIds.Contains(x.Id))
                .ToDictionaryAsync(x => x.Id, x => x.PublicId, cancellationToken);

        return rows.Select(x => new LocationView(
            x.PublicId,
            warehousePublicIds[x.WarehouseId],
            x.ParentLocationId.HasValue
                ? parentPublicIds.GetValueOrDefault(x.ParentLocationId.Value)
                : null,
            x.Code,
            x.Name,
            x.StockBearing,
            StateCode(x.State),
            x.Version)).ToArray();
    }

    public async Task<IReadOnlyList<InventoryStockSummaryView>> ListStockAsync(
        Guid companyId,
        InventoryReadFilter filter,
        CancellationToken cancellationToken)
    {
        var ids = await ResolveReadFilterIdsAsync(companyId, filter, cancellationToken);
        if (ids.Invalid)
            return Array.Empty<InventoryStockSummaryView>();

        var movements = await BuildMovementFilter(
                dbContext.Set<InventoryMovementRecord>().AsNoTracking(),
                companyId,
                ids)
            .Select(x => new
            {
                x.ProductId,
                x.VariantId,
                x.TargetWarehouseId,
                x.TargetDispositionId,
                x.SourceWarehouseId,
                x.SourceDispositionId,
                x.BaseQuantity
            })
            .ToArrayAsync(cancellationToken);

        var reservationRows = await BuildReservationFilter(
                dbContext.Set<InventoryReservationRecord>().AsNoTracking(),
                companyId,
                ids)
            .Select(x => new
            {
                x.Id,
                x.ProductId,
                x.VariantId,
                x.WarehouseId
            })
            .ToArrayAsync(cancellationToken);

        var reservationIds = reservationRows.Select(x => x.Id).ToArray();
        var reservationMovements = reservationIds.Length == 0
            ? Array.Empty<ReservationContribution>()
            : await dbContext.Set<InventoryReservationMovementRecord>()
                .AsNoTracking()
                .Where(x => x.CompanyId == companyId && reservationIds.Contains(x.ReservationId))
                .Select(x => new ReservationContribution(
                    x.ReservationId,
                    x.Kind == ReservationMovementKind.Create ||
                    x.Kind == ReservationMovementKind.Increase
                        ? x.BaseQuantity
                        : -x.BaseQuantity))
                .ToArrayAsync(cancellationToken);

        var reservationTotals = reservationMovements
            .GroupBy(x => x.ReservationId)
            .ToDictionary(x => x.Key, x => x.Sum(y => y.Quantity));

        var balances = new Dictionary<StockKey, MutableStockBalance>();

        foreach (var movement in movements)
        {
            if (movement.TargetWarehouseId.HasValue)
            {
                var key = new StockKey(
                    movement.ProductId,
                    movement.VariantId,
                    movement.TargetWarehouseId.Value);
                var balance = GetOrCreate(balances, key);
                balance.OnHand += movement.BaseQuantity;
                if (movement.TargetDispositionId == (long)InventoryDispositionCode.Available)
                    balance.Available += movement.BaseQuantity;
            }

            if (movement.SourceWarehouseId.HasValue)
            {
                var key = new StockKey(
                    movement.ProductId,
                    movement.VariantId,
                    movement.SourceWarehouseId.Value);
                var balance = GetOrCreate(balances, key);
                balance.OnHand -= movement.BaseQuantity;
                if (movement.SourceDispositionId == (long)InventoryDispositionCode.Available)
                    balance.Available -= movement.BaseQuantity;
            }
        }

        foreach (var reservation in reservationRows)
        {
            var current = reservationTotals.GetValueOrDefault(reservation.Id);
            if (current == 0m)
                continue;

            var key = new StockKey(
                reservation.ProductId,
                reservation.VariantId,
                reservation.WarehouseId);
            GetOrCreate(balances, key).Reserved += current;
        }

        if (balances.Count == 0)
            return Array.Empty<InventoryStockSummaryView>();

        var refs = await LoadPublicReferencesAsync(
            companyId,
            balances.Keys.Select(x => x.ProductId),
            balances.Keys.Where(x => x.VariantId.HasValue).Select(x => x.VariantId!.Value),
            balances.Keys.Select(x => x.WarehouseId),
            Array.Empty<long>(),
            Array.Empty<long>(),
            Array.Empty<long>(),
            cancellationToken);

        return balances
            .Where(x => x.Value.OnHand != 0m || x.Value.Reserved != 0m)
            .OrderBy(x => refs.ProductPublicIds[x.Key.ProductId])
            .ThenBy(x => x.Key.VariantId.HasValue
                ? refs.VariantPublicIds.GetValueOrDefault(x.Key.VariantId.Value)
                : Guid.Empty)
            .ThenBy(x => refs.WarehousePublicIds[x.Key.WarehouseId])
            .Select(x => new InventoryStockSummaryView(
                refs.ProductPublicIds[x.Key.ProductId],
                x.Key.VariantId.HasValue
                    ? refs.VariantPublicIds.GetValueOrDefault(x.Key.VariantId.Value)
                    : null,
                refs.WarehousePublicIds[x.Key.WarehouseId],
                x.Value.OnHand,
                x.Value.Available,
                x.Value.Reserved,
                x.Value.Available - x.Value.Reserved))
            .ToArray();
    }

    public async Task<IReadOnlyList<InventoryPositionBalanceView>> ListPositionsAsync(
        Guid companyId,
        InventoryReadFilter filter,
        CancellationToken cancellationToken)
    {
        var ids = await ResolveReadFilterIdsAsync(companyId, filter, cancellationToken);
        if (ids.Invalid)
            return Array.Empty<InventoryPositionBalanceView>();

        var movements = await BuildMovementFilter(
                dbContext.Set<InventoryMovementRecord>().AsNoTracking(),
                companyId,
                ids)
            .Select(x => new
            {
                x.ProductId,
                x.VariantId,
                x.TargetWarehouseId,
                x.TargetLocationId,
                x.TargetDispositionId,
                x.SourceWarehouseId,
                x.SourceLocationId,
                x.SourceDispositionId,
                x.LotId,
                x.SerialId,
                x.BaseQuantity
            })
            .ToArrayAsync(cancellationToken);

        var balances = new Dictionary<PositionKey, decimal>();

        foreach (var movement in movements)
        {
            if (movement.TargetWarehouseId.HasValue &&
                movement.TargetDispositionId.HasValue)
            {
                var key = new PositionKey(
                    movement.ProductId,
                    movement.VariantId,
                    movement.TargetWarehouseId.Value,
                    movement.TargetLocationId,
                    movement.TargetDispositionId.Value,
                    movement.LotId,
                    movement.SerialId);
                balances[key] = balances.GetValueOrDefault(key) + movement.BaseQuantity;
            }

            if (movement.SourceWarehouseId.HasValue &&
                movement.SourceDispositionId.HasValue)
            {
                var key = new PositionKey(
                    movement.ProductId,
                    movement.VariantId,
                    movement.SourceWarehouseId.Value,
                    movement.SourceLocationId,
                    movement.SourceDispositionId.Value,
                    movement.LotId,
                    movement.SerialId);
                balances[key] = balances.GetValueOrDefault(key) - movement.BaseQuantity;
            }
        }

        var nonZero = balances.Where(x => x.Value != 0m).ToArray();
        if (nonZero.Length == 0)
            return Array.Empty<InventoryPositionBalanceView>();

        var refs = await LoadPublicReferencesAsync(
            companyId,
            nonZero.Select(x => x.Key.ProductId),
            nonZero.Where(x => x.Key.VariantId.HasValue).Select(x => x.Key.VariantId!.Value),
            nonZero.Select(x => x.Key.WarehouseId),
            nonZero.Where(x => x.Key.LocationId.HasValue).Select(x => x.Key.LocationId!.Value),
            nonZero.Where(x => x.Key.LotId.HasValue).Select(x => x.Key.LotId!.Value),
            nonZero.Where(x => x.Key.SerialId.HasValue).Select(x => x.Key.SerialId!.Value),
            cancellationToken);

        return nonZero
            .OrderBy(x => refs.ProductPublicIds[x.Key.ProductId])
            .ThenBy(x => refs.WarehousePublicIds[x.Key.WarehouseId])
            .ThenBy(x => x.Key.LocationId ?? 0)
            .Select(x => new InventoryPositionBalanceView(
                refs.ProductPublicIds[x.Key.ProductId],
                x.Key.VariantId.HasValue
                    ? refs.VariantPublicIds.GetValueOrDefault(x.Key.VariantId.Value)
                    : null,
                refs.WarehousePublicIds[x.Key.WarehouseId],
                x.Key.LocationId.HasValue
                    ? refs.LocationPublicIds.GetValueOrDefault(x.Key.LocationId.Value)
                    : null,
                DispositionCode((InventoryDispositionCode)x.Key.DispositionId),
                x.Key.LotId.HasValue
                    ? refs.LotPublicIds.GetValueOrDefault(x.Key.LotId.Value)
                    : null,
                x.Key.SerialId.HasValue
                    ? refs.SerialPublicIds.GetValueOrDefault(x.Key.SerialId.Value)
                    : null,
                x.Value))
            .ToArray();
    }

    public async Task<IReadOnlyList<InventoryMovementView>> ListMovementsAsync(
        Guid companyId,
        InventoryReadFilter filter,
        CancellationToken cancellationToken)
    {
        var ids = await ResolveReadFilterIdsAsync(companyId, filter, cancellationToken);
        if (ids.Invalid)
            return Array.Empty<InventoryMovementView>();

        var rows = await BuildMovementFilter(
                dbContext.Set<InventoryMovementRecord>().AsNoTracking(),
                companyId,
                ids)
            .OrderByDescending(x => x.PostedAt)
            .ThenByDescending(x => x.Id)
            .Take(500)
            .ToArrayAsync(cancellationToken);

        return await MapMovementRowsAsync(companyId, rows, cancellationToken);
    }

    public async Task<IReadOnlyList<LotTraceView>> ListLotsAsync(
        Guid companyId,
        Guid? productPublicId,
        Guid? variantPublicId,
        CancellationToken cancellationToken)
    {
        long? productId = null;
        long? variantId = null;

        if (productPublicId.HasValue)
        {
            productId = await dbContext.Set<ProductRecord>()
                .AsNoTracking()
                .Where(x => x.CompanyId == companyId && x.PublicId == productPublicId.Value)
                .Select(x => (long?)x.Id)
                .SingleOrDefaultAsync(cancellationToken);
            if (!productId.HasValue)
                return Array.Empty<LotTraceView>();
        }

        if (variantPublicId.HasValue)
        {
            variantId = await dbContext.Set<ProductVariantRecord>()
                .AsNoTracking()
                .Where(x => x.CompanyId == companyId && x.PublicId == variantPublicId.Value)
                .Select(x => (long?)x.Id)
                .SingleOrDefaultAsync(cancellationToken);
            if (!variantId.HasValue)
                return Array.Empty<LotTraceView>();
        }

        var lots = await dbContext.Set<InventoryLotRecord>()
            .AsNoTracking()
            .Where(x => x.CompanyId == companyId &&
                        (!productId.HasValue || x.ProductId == productId.Value) &&
                        (!variantId.HasValue || x.VariantId == variantId.Value))
            .OrderBy(x => x.Code)
            .Take(500)
            .ToArrayAsync(cancellationToken);

        if (lots.Length == 0)
            return Array.Empty<LotTraceView>();

        var lotIds = lots.Select(x => x.Id).ToArray();
        var incoming = await dbContext.Set<InventoryMovementRecord>()
            .AsNoTracking()
            .Where(x => x.CompanyId == companyId &&
                        x.LotId.HasValue &&
                        lotIds.Contains(x.LotId.Value) &&
                        x.TargetWarehouseId != null)
            .GroupBy(x => x.LotId!.Value)
            .Select(x => new { LotId = x.Key, Quantity = x.Sum(y => y.BaseQuantity) })
            .ToDictionaryAsync(x => x.LotId, x => x.Quantity, cancellationToken);
        var outgoing = await dbContext.Set<InventoryMovementRecord>()
            .AsNoTracking()
            .Where(x => x.CompanyId == companyId &&
                        x.LotId.HasValue &&
                        lotIds.Contains(x.LotId.Value) &&
                        x.SourceWarehouseId != null)
            .GroupBy(x => x.LotId!.Value)
            .Select(x => new { LotId = x.Key, Quantity = x.Sum(y => y.BaseQuantity) })
            .ToDictionaryAsync(x => x.LotId, x => x.Quantity, cancellationToken);

        var refs = await LoadPublicReferencesAsync(
            companyId,
            lots.Select(x => x.ProductId),
            lots.Where(x => x.VariantId.HasValue).Select(x => x.VariantId!.Value),
            Array.Empty<long>(),
            Array.Empty<long>(),
            Array.Empty<long>(),
            Array.Empty<long>(),
            cancellationToken);

        return lots.Select(x => new LotTraceView(
            x.PublicId,
            refs.ProductPublicIds[x.ProductId],
            x.VariantId.HasValue
                ? refs.VariantPublicIds.GetValueOrDefault(x.VariantId.Value)
                : null,
            x.Code,
            x.ManufactureDate,
            x.ExpiryDate,
            incoming.GetValueOrDefault(x.Id) - outgoing.GetValueOrDefault(x.Id),
            x.Version)).ToArray();
    }

    public async Task<SerialTraceView?> GetSerialAsync(
        Guid companyId,
        Guid serialPublicId,
        CancellationToken cancellationToken)
    {
        var serial = await dbContext.Set<InventorySerialRecord>()
            .AsNoTracking()
            .SingleOrDefaultAsync(
                x => x.CompanyId == companyId && x.PublicId == serialPublicId,
                cancellationToken);
        if (serial is null)
            return null;

        var rows = await dbContext.Set<InventoryMovementRecord>()
            .AsNoTracking()
            .Where(x => x.CompanyId == companyId && x.SerialId == serial.Id)
            .OrderByDescending(x => x.PostedAt)
            .ThenByDescending(x => x.Id)
            .Take(500)
            .ToArrayAsync(cancellationToken);

        var movements = await MapMovementRowsAsync(companyId, rows, cancellationToken);
        var onHand = rows.Sum(x => x.TargetWarehouseId.HasValue ? x.BaseQuantity : 0m) -
                     rows.Sum(x => x.SourceWarehouseId.HasValue ? x.BaseQuantity : 0m);

        Guid? warehousePublicId = null;
        Guid? locationPublicId = null;
        string? disposition = null;

        if (onHand == 1m)
        {
            var balances = new Dictionary<SerialPositionKey, decimal>();
            foreach (var row in rows)
            {
                if (row.TargetWarehouseId.HasValue && row.TargetDispositionId.HasValue)
                {
                    var key = new SerialPositionKey(
                        row.TargetWarehouseId.Value,
                        row.TargetLocationId,
                        row.TargetDispositionId.Value);
                    balances[key] = balances.GetValueOrDefault(key) + row.BaseQuantity;
                }
                if (row.SourceWarehouseId.HasValue && row.SourceDispositionId.HasValue)
                {
                    var key = new SerialPositionKey(
                        row.SourceWarehouseId.Value,
                        row.SourceLocationId,
                        row.SourceDispositionId.Value);
                    balances[key] = balances.GetValueOrDefault(key) - row.BaseQuantity;
                }
            }

            var current = balances.SingleOrDefault(x => x.Value == 1m);
            if (!current.Equals(default(KeyValuePair<SerialPositionKey, decimal>)))
            {
                var refs = await LoadPublicReferencesAsync(
                    companyId,
                    new[] { serial.ProductId },
                    serial.VariantId.HasValue ? new[] { serial.VariantId.Value } : Array.Empty<long>(),
                    new[] { current.Key.WarehouseId },
                    current.Key.LocationId.HasValue ? new[] { current.Key.LocationId.Value } : Array.Empty<long>(),
                    serial.LotId.HasValue ? new[] { serial.LotId.Value } : Array.Empty<long>(),
                    new[] { serial.Id },
                    cancellationToken);

                warehousePublicId = refs.WarehousePublicIds.GetValueOrDefault(current.Key.WarehouseId);
                locationPublicId = current.Key.LocationId.HasValue
                    ? refs.LocationPublicIds.GetValueOrDefault(current.Key.LocationId.Value)
                    : null;
                disposition = DispositionCode((InventoryDispositionCode)current.Key.DispositionId);
            }
        }

        var identityRefs = await LoadPublicReferencesAsync(
            companyId,
            new[] { serial.ProductId },
            serial.VariantId.HasValue ? new[] { serial.VariantId.Value } : Array.Empty<long>(),
            Array.Empty<long>(),
            Array.Empty<long>(),
            serial.LotId.HasValue ? new[] { serial.LotId.Value } : Array.Empty<long>(),
            new[] { serial.Id },
            cancellationToken);

        return new SerialTraceView(
            serial.PublicId,
            identityRefs.ProductPublicIds[serial.ProductId],
            serial.VariantId.HasValue
                ? identityRefs.VariantPublicIds.GetValueOrDefault(serial.VariantId.Value)
                : null,
            serial.LotId.HasValue
                ? identityRefs.LotPublicIds.GetValueOrDefault(serial.LotId.Value)
                : null,
            serial.Value,
            warehousePublicId,
            locationPublicId,
            disposition,
            onHand == 1m,
            serial.Version,
            movements);
    }

    public async Task<IReadOnlyList<ReservationView>> ListReservationsAsync(
        Guid companyId,
        Guid? productPublicId,
        Guid? warehousePublicId,
        CancellationToken cancellationToken)
    {
        long? productId = null;
        long? warehouseId = null;

        if (productPublicId.HasValue)
        {
            productId = await dbContext.Set<ProductRecord>()
                .AsNoTracking()
                .Where(x => x.CompanyId == companyId && x.PublicId == productPublicId.Value)
                .Select(x => (long?)x.Id)
                .SingleOrDefaultAsync(cancellationToken);
            if (!productId.HasValue)
                return Array.Empty<ReservationView>();
        }

        if (warehousePublicId.HasValue)
        {
            warehouseId = await dbContext.Set<WarehouseRecord>()
                .AsNoTracking()
                .Where(x => x.CompanyId == companyId && x.PublicId == warehousePublicId.Value)
                .Select(x => (long?)x.Id)
                .SingleOrDefaultAsync(cancellationToken);
            if (!warehouseId.HasValue)
                return Array.Empty<ReservationView>();
        }

        var reservations = await dbContext.Set<InventoryReservationRecord>()
            .AsNoTracking()
            .Where(x => x.CompanyId == companyId &&
                        (!productId.HasValue || x.ProductId == productId.Value) &&
                        (!warehouseId.HasValue || x.WarehouseId == warehouseId.Value))
            .OrderByDescending(x => x.CreatedAt)
            .Take(500)
            .ToArrayAsync(cancellationToken);

        if (reservations.Length == 0)
            return Array.Empty<ReservationView>();

        var ids = reservations.Select(x => x.Id).ToArray();
        var movements = await dbContext.Set<InventoryReservationMovementRecord>()
            .AsNoTracking()
            .Where(x => x.CompanyId == companyId && ids.Contains(x.ReservationId))
            .OrderBy(x => x.OccurredAt)
            .ThenBy(x => x.Id)
            .ToArrayAsync(cancellationToken);
        var movementsByReservation = movements
            .GroupBy(x => x.ReservationId)
            .ToDictionary(x => x.Key, x => x.ToArray());

        var refs = await LoadPublicReferencesAsync(
            companyId,
            reservations.Select(x => x.ProductId),
            reservations.Where(x => x.VariantId.HasValue).Select(x => x.VariantId!.Value),
            reservations.Select(x => x.WarehouseId),
            Array.Empty<long>(),
            Array.Empty<long>(),
            Array.Empty<long>(),
            cancellationToken);

        var uomIds = reservations.Select(x => x.UomId).Distinct().ToArray();
        var uomPublicIds = await dbContext.Set<UnitOfMeasureRecord>()
            .AsNoTracking()
            .Where(x => x.CompanyId == companyId && uomIds.Contains(x.Id))
            .ToDictionaryAsync(x => x.Id, x => x.PublicId, cancellationToken);

        return reservations.Select(reservation =>
        {
            var history = movementsByReservation.GetValueOrDefault(reservation.Id) ??
                          Array.Empty<InventoryReservationMovementRecord>();
            var current = history.Sum(x =>
                x.Kind == ReservationMovementKind.Create ||
                x.Kind == ReservationMovementKind.Increase
                    ? x.BaseQuantity
                    : -x.BaseQuantity);

            return new ReservationView(
                reservation.PublicId,
                reservation.SalesOrderPublicId,
                reservation.SalesOrderVersion,
                reservation.SalesOrderLinePublicId,
                refs.ProductPublicIds[reservation.ProductId],
                reservation.VariantId.HasValue
                    ? refs.VariantPublicIds.GetValueOrDefault(reservation.VariantId.Value)
                    : null,
                refs.WarehousePublicIds[reservation.WarehouseId],
                uomPublicIds[reservation.UomId],
                reservation.ConversionFactorSnapshot,
                current,
                reservation.CreatedAt,
                history.Select(x => new ReservationMovementView(
                    x.PublicId,
                    ReservationKindCode(x.Kind),
                    x.EnteredQuantity,
                    x.BaseQuantity,
                    x.SourceModule,
                    x.SourceDocumentPublicId,
                    x.SourceLinePublicId,
                    x.ActorId,
                    x.CorrelationId,
                    x.OccurredAt)).ToArray());
        }).ToArray();
    }

    private async Task<IReadOnlyList<InventoryMovementView>> MapMovementRowsAsync(
        Guid companyId,
        IReadOnlyList<InventoryMovementRecord> rows,
        CancellationToken cancellationToken)
    {
        if (rows.Count == 0)
            return Array.Empty<InventoryMovementView>();

        var refs = await LoadPublicReferencesAsync(
            companyId,
            rows.Select(x => x.ProductId),
            rows.Where(x => x.VariantId.HasValue).Select(x => x.VariantId!.Value),
            rows.SelectMany(x => new long?[] { x.SourceWarehouseId, x.TargetWarehouseId })
                .Where(x => x.HasValue).Select(x => x!.Value),
            rows.SelectMany(x => new long?[] { x.SourceLocationId, x.TargetLocationId })
                .Where(x => x.HasValue).Select(x => x!.Value),
            rows.Where(x => x.LotId.HasValue).Select(x => x.LotId!.Value),
            rows.Where(x => x.SerialId.HasValue).Select(x => x.SerialId!.Value),
            cancellationToken);

        var uomIds = rows.Select(x => x.UomId).Distinct().ToArray();
        var uomPublicIds = await dbContext.Set<UnitOfMeasureRecord>()
            .AsNoTracking()
            .Where(x => x.CompanyId == companyId && uomIds.Contains(x.Id))
            .ToDictionaryAsync(x => x.Id, x => x.PublicId, cancellationToken);

        var reversalIds = rows.Where(x => x.ReversalOfMovementId.HasValue)
            .Select(x => x.ReversalOfMovementId!.Value)
            .Distinct()
            .ToArray();
        var reversalPublicIds = reversalIds.Length == 0
            ? new Dictionary<long, Guid>()
            : await dbContext.Set<InventoryMovementRecord>()
                .AsNoTracking()
                .Where(x => x.CompanyId == companyId && reversalIds.Contains(x.Id))
                .ToDictionaryAsync(x => x.Id, x => x.PublicId, cancellationToken);

        return rows.Select(x => new InventoryMovementView(
            x.PublicId,
            refs.ProductPublicIds[x.ProductId],
            x.VariantId.HasValue
                ? refs.VariantPublicIds.GetValueOrDefault(x.VariantId.Value)
                : null,
            uomPublicIds[x.UomId],
            x.EnteredQuantity,
            x.ConversionFactorSnapshot,
            x.BaseQuantity,
            x.SourceWarehouseId.HasValue
                ? refs.WarehousePublicIds.GetValueOrDefault(x.SourceWarehouseId.Value)
                : null,
            x.SourceLocationId.HasValue
                ? refs.LocationPublicIds.GetValueOrDefault(x.SourceLocationId.Value)
                : null,
            x.SourceDispositionId.HasValue
                ? DispositionCode((InventoryDispositionCode)x.SourceDispositionId.Value)
                : null,
            x.TargetWarehouseId.HasValue
                ? refs.WarehousePublicIds.GetValueOrDefault(x.TargetWarehouseId.Value)
                : null,
            x.TargetLocationId.HasValue
                ? refs.LocationPublicIds.GetValueOrDefault(x.TargetLocationId.Value)
                : null,
            x.TargetDispositionId.HasValue
                ? DispositionCode((InventoryDispositionCode)x.TargetDispositionId.Value)
                : null,
            x.LotId.HasValue
                ? refs.LotPublicIds.GetValueOrDefault(x.LotId.Value)
                : null,
            x.SerialId.HasValue
                ? refs.SerialPublicIds.GetValueOrDefault(x.SerialId.Value)
                : null,
            x.SourceModule,
            x.SourceEntityType,
            x.SourceDocumentPublicId,
            x.SourceLinePublicId,
            x.ReversalOfMovementId.HasValue
                ? reversalPublicIds.GetValueOrDefault(x.ReversalOfMovementId.Value)
                : null,
            x.PostedAt,
            x.ActorId,
            x.CorrelationId)).ToArray();
    }

    private async Task<ResolvedReadFilter> ResolveReadFilterIdsAsync(
        Guid companyId,
        InventoryReadFilter filter,
        CancellationToken cancellationToken)
    {
        long? productId = null;
        long? variantId = null;
        long? warehouseId = null;
        long? locationId = null;
        long? lotId = null;
        long? serialId = null;

        if (filter.ProductPublicId.HasValue)
        {
            productId = await dbContext.Set<ProductRecord>().AsNoTracking()
                .Where(x => x.CompanyId == companyId && x.PublicId == filter.ProductPublicId.Value)
                .Select(x => (long?)x.Id)
                .SingleOrDefaultAsync(cancellationToken);
            if (!productId.HasValue) return ResolvedReadFilter.InvalidFilter;
        }

        if (filter.VariantPublicId.HasValue)
        {
            variantId = await dbContext.Set<ProductVariantRecord>().AsNoTracking()
                .Where(x => x.CompanyId == companyId && x.PublicId == filter.VariantPublicId.Value)
                .Select(x => (long?)x.Id)
                .SingleOrDefaultAsync(cancellationToken);
            if (!variantId.HasValue) return ResolvedReadFilter.InvalidFilter;
        }

        if (filter.WarehousePublicId.HasValue)
        {
            warehouseId = await dbContext.Set<WarehouseRecord>().AsNoTracking()
                .Where(x => x.CompanyId == companyId && x.PublicId == filter.WarehousePublicId.Value)
                .Select(x => (long?)x.Id)
                .SingleOrDefaultAsync(cancellationToken);
            if (!warehouseId.HasValue) return ResolvedReadFilter.InvalidFilter;
        }

        if (filter.LocationPublicId.HasValue)
        {
            locationId = await dbContext.Set<LocationRecord>().AsNoTracking()
                .Where(x => x.CompanyId == companyId && x.PublicId == filter.LocationPublicId.Value)
                .Select(x => (long?)x.Id)
                .SingleOrDefaultAsync(cancellationToken);
            if (!locationId.HasValue) return ResolvedReadFilter.InvalidFilter;
        }

        if (filter.LotPublicId.HasValue)
        {
            lotId = await dbContext.Set<InventoryLotRecord>().AsNoTracking()
                .Where(x => x.CompanyId == companyId && x.PublicId == filter.LotPublicId.Value)
                .Select(x => (long?)x.Id)
                .SingleOrDefaultAsync(cancellationToken);
            if (!lotId.HasValue) return ResolvedReadFilter.InvalidFilter;
        }

        if (filter.SerialPublicId.HasValue)
        {
            serialId = await dbContext.Set<InventorySerialRecord>().AsNoTracking()
                .Where(x => x.CompanyId == companyId && x.PublicId == filter.SerialPublicId.Value)
                .Select(x => (long?)x.Id)
                .SingleOrDefaultAsync(cancellationToken);
            if (!serialId.HasValue) return ResolvedReadFilter.InvalidFilter;
        }

        return new ResolvedReadFilter(
            false,
            productId,
            variantId,
            warehouseId,
            locationId,
            filter.Disposition.HasValue ? (long?)filter.Disposition.Value : null,
            lotId,
            serialId);
    }

    private static IQueryable<InventoryMovementRecord> BuildMovementFilter(
        IQueryable<InventoryMovementRecord> query,
        Guid companyId,
        ResolvedReadFilter filter)
    {
        query = query.Where(x => x.CompanyId == companyId);
        if (filter.ProductId.HasValue)
            query = query.Where(x => x.ProductId == filter.ProductId.Value);
        if (filter.VariantId.HasValue)
            query = query.Where(x => x.VariantId == filter.VariantId.Value);
        if (filter.WarehouseId.HasValue)
            query = query.Where(x =>
                x.SourceWarehouseId == filter.WarehouseId.Value ||
                x.TargetWarehouseId == filter.WarehouseId.Value);
        if (filter.LocationId.HasValue)
            query = query.Where(x =>
                x.SourceLocationId == filter.LocationId.Value ||
                x.TargetLocationId == filter.LocationId.Value);
        if (filter.DispositionId.HasValue)
            query = query.Where(x =>
                x.SourceDispositionId == filter.DispositionId.Value ||
                x.TargetDispositionId == filter.DispositionId.Value);
        if (filter.LotId.HasValue)
            query = query.Where(x => x.LotId == filter.LotId.Value);
        if (filter.SerialId.HasValue)
            query = query.Where(x => x.SerialId == filter.SerialId.Value);
        return query;
    }

    private static IQueryable<InventoryReservationRecord> BuildReservationFilter(
        IQueryable<InventoryReservationRecord> query,
        Guid companyId,
        ResolvedReadFilter filter)
    {
        query = query.Where(x => x.CompanyId == companyId);
        if (filter.ProductId.HasValue)
            query = query.Where(x => x.ProductId == filter.ProductId.Value);
        if (filter.VariantId.HasValue)
            query = query.Where(x => x.VariantId == filter.VariantId.Value);
        if (filter.WarehouseId.HasValue)
            query = query.Where(x => x.WarehouseId == filter.WarehouseId.Value);
        return query;
    }

    private async Task<PublicReferenceMaps> LoadPublicReferencesAsync(
        Guid companyId,
        IEnumerable<long> productIds,
        IEnumerable<long> variantIds,
        IEnumerable<long> warehouseIds,
        IEnumerable<long> locationIds,
        IEnumerable<long> lotIds,
        IEnumerable<long> serialIds,
        CancellationToken cancellationToken)
    {
        var productSet = productIds.Distinct().ToArray();
        var variantSet = variantIds.Distinct().ToArray();
        var warehouseSet = warehouseIds.Distinct().ToArray();
        var locationSet = locationIds.Distinct().ToArray();
        var lotSet = lotIds.Distinct().ToArray();
        var serialSet = serialIds.Distinct().ToArray();

        var products = productSet.Length == 0
            ? new Dictionary<long, Guid>()
            : await dbContext.Set<ProductRecord>().AsNoTracking()
                .Where(x => x.CompanyId == companyId && productSet.Contains(x.Id))
                .ToDictionaryAsync(x => x.Id, x => x.PublicId, cancellationToken);
        var variants = variantSet.Length == 0
            ? new Dictionary<long, Guid>()
            : await dbContext.Set<ProductVariantRecord>().AsNoTracking()
                .Where(x => x.CompanyId == companyId && variantSet.Contains(x.Id))
                .ToDictionaryAsync(x => x.Id, x => x.PublicId, cancellationToken);
        var warehouses = warehouseSet.Length == 0
            ? new Dictionary<long, Guid>()
            : await dbContext.Set<WarehouseRecord>().AsNoTracking()
                .Where(x => x.CompanyId == companyId && warehouseSet.Contains(x.Id))
                .ToDictionaryAsync(x => x.Id, x => x.PublicId, cancellationToken);
        var locations = locationSet.Length == 0
            ? new Dictionary<long, Guid>()
            : await dbContext.Set<LocationRecord>().AsNoTracking()
                .Where(x => x.CompanyId == companyId && locationSet.Contains(x.Id))
                .ToDictionaryAsync(x => x.Id, x => x.PublicId, cancellationToken);
        var lots = lotSet.Length == 0
            ? new Dictionary<long, Guid>()
            : await dbContext.Set<InventoryLotRecord>().AsNoTracking()
                .Where(x => x.CompanyId == companyId && lotSet.Contains(x.Id))
                .ToDictionaryAsync(x => x.Id, x => x.PublicId, cancellationToken);
        var serials = serialSet.Length == 0
            ? new Dictionary<long, Guid>()
            : await dbContext.Set<InventorySerialRecord>().AsNoTracking()
                .Where(x => x.CompanyId == companyId && serialSet.Contains(x.Id))
                .ToDictionaryAsync(x => x.Id, x => x.PublicId, cancellationToken);

        return new PublicReferenceMaps(
            products,
            variants,
            warehouses,
            locations,
            lots,
            serials);
    }

    private static MutableStockBalance GetOrCreate(
        IDictionary<StockKey, MutableStockBalance> balances,
        StockKey key)
    {
        if (!balances.TryGetValue(key, out var balance))
        {
            balance = new MutableStockBalance();
            balances.Add(key, balance);
        }

        return balance;
    }

    private static string DispositionCode(InventoryDispositionCode code) => code switch
    {
        InventoryDispositionCode.Available => "AVAILABLE",
        InventoryDispositionCode.Quarantine => "QUARANTINE",
        InventoryDispositionCode.QualityHold => "QUALITY_HOLD",
        InventoryDispositionCode.Rework => "REWORK",
        InventoryDispositionCode.Damaged => "DAMAGED",
        InventoryDispositionCode.Transit => "TRANSIT",
        _ => throw new ArgumentOutOfRangeException(nameof(code))
    };

    private static string ReservationKindCode(ReservationMovementKind kind) => kind switch
    {
        ReservationMovementKind.Create => "CREATE",
        ReservationMovementKind.Increase => "INCREASE",
        ReservationMovementKind.Release => "RELEASE",
        ReservationMovementKind.Consume => "CONSUME",
        _ => throw new ArgumentOutOfRangeException(nameof(kind))
    };

    private sealed record ResolvedReadFilter(
        bool Invalid,
        long? ProductId,
        long? VariantId,
        long? WarehouseId,
        long? LocationId,
        long? DispositionId,
        long? LotId,
        long? SerialId)
    {
        public static ResolvedReadFilter InvalidFilter { get; } =
            new(true, null, null, null, null, null, null, null);
    }

    private sealed record StockKey(
        long ProductId,
        long? VariantId,
        long WarehouseId);

    private sealed class MutableStockBalance
    {
        public decimal OnHand { get; set; }
        public decimal Available { get; set; }
        public decimal Reserved { get; set; }
    }

    private sealed record PositionKey(
        long ProductId,
        long? VariantId,
        long WarehouseId,
        long? LocationId,
        long DispositionId,
        long? LotId,
        long? SerialId);

    private sealed record SerialPositionKey(
        long WarehouseId,
        long? LocationId,
        long DispositionId);

    private sealed record ReservationContribution(
        long ReservationId,
        decimal Quantity);

    private sealed record PublicReferenceMaps(
        IReadOnlyDictionary<long, Guid> ProductPublicIds,
        IReadOnlyDictionary<long, Guid> VariantPublicIds,
        IReadOnlyDictionary<long, Guid> WarehousePublicIds,
        IReadOnlyDictionary<long, Guid> LocationPublicIds,
        IReadOnlyDictionary<long, Guid> LotPublicIds,
        IReadOnlyDictionary<long, Guid> SerialPublicIds);
}
