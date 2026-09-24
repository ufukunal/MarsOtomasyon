using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Configuration;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Inventory;
using Mars.Domain.Inventory;
using Mars.Infrastructure.Persistence;
using Microsoft.EntityFrameworkCore;
using MarsExecutionContext = Mars.Application.Foundation.Context.ExecutionContext;

internal static class InventoryImp001Tests
{
    public static IReadOnlyList<(string Name, Action Test)> Cases { get; } =
    [
        ("Inventory dispositions are fixed and exclude RESERVED", DispositionsAreFixed),
        ("Inventory domain validates hierarchy and lot dates", DomainInvariants),
        ("Inventory stock read requires inventory.stock.read", StockReadRequiresPermission),
        ("Inventory Warehouse create is company-scoped audited and idempotent", WarehouseCreateProducesScopedWrite),
        ("Inventory physical posting validates positive snapshot quantity and physical side", MovementContractValidation),
        ("Inventory Reservation requires exact Sales source identity", ReservationContractValidation),
        ("INVENTORY-IMP-001 model contains normalized authority tables", ModelContainsAuthorityTables)
    ];

    private static void DispositionsAreFixed()
    {
        var names = Enum.GetNames<InventoryDispositionCode>();
        AssertSequenceEqual(
            new[] { "Available", "Quarantine", "QualityHold", "Rework", "Damaged", "Transit" },
            names);
        AssertTrue(!names.Contains("Reserved", StringComparer.OrdinalIgnoreCase));
    }

    private static void DomainInvariants()
    {
        var companyId = Guid.NewGuid();
        var warehouseId = Guid.NewGuid();
        var locationId = Guid.NewGuid();
        var now = DateTimeOffset.UtcNow;

        AssertThrows<ArgumentException>(() =>
            Location.Create(
                locationId,
                companyId,
                warehouseId,
                locationId,
                "BIN-1",
                "Bin",
                true,
                now));

        AssertThrows<ArgumentException>(() =>
            Lot.Create(
                Guid.NewGuid(),
                companyId,
                Guid.NewGuid(),
                null,
                "LOT-1",
                new DateOnly(2026, 9, 24),
                new DateOnly(2026, 9, 23),
                now));

        var warehouse = Warehouse.Create(
            Guid.NewGuid(),
            companyId,
            "WH-1",
            "Main",
            now);
        AssertEqual(InventoryMasterState.Active, warehouse.State);
        AssertEqual(1L, warehouse.Version);
    }

    private static void StockReadRequiresPermission()
    {
        var handler = new InventoryQueryHandler(
            new FakePermissionEvaluator(),
            new FakeReadPersistence());

        var result = handler.ListStockAsync(
                new InventoryReadFilter(),
                NewContext(),
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(ErrorCategory.Authorization, result.Error!.Category);
    }

    private static void WarehouseCreateProducesScopedWrite()
    {
        var persistence = new FakeMutationPersistence();
        var handler = new InventoryMasterCommandHandler(
            new FakePermissionEvaluator(InventoryPermissions.WarehouseManage),
            persistence);
        var context = NewContext();

        var result = handler.CreateWarehouseAsync(
                "WH-1",
                "Main Warehouse",
                "warehouse-create-op",
                context,
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsSuccess);
        AssertTrue(persistence.CreateWarehouseWrite is not null);
        AssertEqual(context.CompanyId, persistence.CreateWarehouseWrite!.Context.CompanyId);
        AssertEqual(
            "inventory.warehouse.create:" + context.CompanyId.ToString("D"),
            persistence.CreateWarehouseWrite.Context.Idempotency.Scope);
        AssertEqual(
            "WarehouseCreated",
            persistence.CreateWarehouseWrite.Context.Audit.Action);
    }

    private static void MovementContractValidation()
    {
        var persistence = new FakeAuthorityPersistence();
        var authority = new InventoryAuthorityService(persistence);

        var result = authority.PostAsync(
                new InventoryMovementCommand(
                    Guid.NewGuid(),
                    null,
                    Guid.NewGuid(),
                    1m,
                    0m,
                    null,
                    null,
                    InventorySourceIdentity.Create(
                        "Warehouse",
                        "TestSource",
                        Guid.NewGuid(),
                        null),
                    null,
                    "move-op"),
                NewContext(),
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(ErrorCategory.Validation, result.Error!.Category);
        AssertEqual(0, persistence.PostCalls);
    }

    private static void ReservationContractValidation()
    {
        var persistence = new FakeAuthorityPersistence();
        var authority = new InventoryAuthorityService(persistence);

        var result = authority.CreateAsync(
                new CreateReservationCommand(
                    Guid.Empty,
                    1,
                    Guid.NewGuid(),
                    Guid.NewGuid(),
                    null,
                    Guid.NewGuid(),
                    Guid.NewGuid(),
                    1m,
                    1m,
                    "reservation-op"),
                NewContext(),
                CancellationToken.None)
            .GetAwaiter()
            .GetResult();

        AssertTrue(result.IsFailure);
        AssertEqual(ErrorCategory.Validation, result.Error!.Category);
        AssertEqual(0, persistence.ReservationCreateCalls);
    }

    private static void ModelContainsAuthorityTables()
    {
        using var context = CreateModelContext();
        var tables = context.Model.GetEntityTypes()
            .Select(x => x.GetSchema() + "." + x.GetTableName())
            .ToHashSet(StringComparer.Ordinal);

        foreach (var table in new[]
                 {
                     "inventory.warehouses",
                     "inventory.locations",
                     "inventory.dispositions",
                     "inventory.lots",
                     "inventory.serials",
                     "inventory.movements",
                     "inventory.reservations",
                     "inventory.reservation_movements"
                 })
        {
            AssertTrue(tables.Contains(table));
        }

        var movement = context.Model.GetEntityTypes()
            .Single(x => x.GetTableName() == "movements" && x.GetSchema() == "inventory");
        AssertEqual(28, movement.FindProperty("EnteredQuantity")?.GetPrecision());
        AssertEqual(9, movement.FindProperty("EnteredQuantity")?.GetScale());
        AssertEqual(28, movement.FindProperty("ConversionFactorSnapshot")?.GetPrecision());
        AssertEqual(9, movement.FindProperty("ConversionFactorSnapshot")?.GetScale());
        AssertEqual(38, movement.FindProperty("BaseQuantity")?.GetPrecision());
        AssertEqual(18, movement.FindProperty("BaseQuantity")?.GetScale());
        AssertTrue(movement.FindProperty("StockQuantity") is null);

    }

    private static MarsDbContext CreateModelContext()
    {
        var options = MarsDbContextOptions.CreateRuntime(
            new PostgreSqlRuntimeOptions(
                "Host=localhost;Database=mars_inventory_imp_001_model_probe"));
        return new MarsDbContext(options);
    }

    private static IExecutionContext NewContext() =>
        new MarsExecutionContext(
            Guid.Parse("aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"),
            Guid.Parse("bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb"),
            null,
            new CorrelationId("corr-inventory-imp-001"));

    private static void AssertTrue(bool condition)
    {
        if (!condition)
            throw new InvalidOperationException("Assertion failed.");
    }

    private static void AssertEqual<T>(T expected, T actual)
    {
        if (!EqualityComparer<T>.Default.Equals(expected, actual))
            throw new InvalidOperationException(
                $"Expected '{expected}', actual '{actual}'.");
    }

    private static void AssertSequenceEqual<T>(
        IReadOnlyList<T> expected,
        IReadOnlyList<T> actual)
    {
        if (expected.Count != actual.Count)
            throw new InvalidOperationException(
                $"Expected {expected.Count} items, actual {actual.Count}.");

        for (var index = 0; index < expected.Count; index++)
        {
            if (!EqualityComparer<T>.Default.Equals(expected[index], actual[index]))
                throw new InvalidOperationException(
                    $"Sequence differs at {index}.");
        }
    }

    private static TException AssertThrows<TException>(Action action)
        where TException : Exception
    {
        try
        {
            action();
        }
        catch (TException exception)
        {
            return exception;
        }

        throw new InvalidOperationException(
            "Expected exception was not thrown.");
    }

    private sealed class FakePermissionEvaluator(params string[] permissions)
        : IPermissionEvaluator
    {
        private readonly HashSet<string> granted =
            new(permissions, StringComparer.Ordinal);

        public Task<bool> IsGrantedAsync(
            Guid actorId,
            Guid companyId,
            string permissionCode,
            CancellationToken cancellationToken) =>
            Task.FromResult(granted.Contains(permissionCode));
    }

    private sealed class FakeReadPersistence : IInventoryReadPersistence
    {
        public Task<IReadOnlyList<WarehouseView>> ListWarehousesAsync(
            Guid companyId,
            string? search,
            CancellationToken cancellationToken) =>
            Task.FromResult<IReadOnlyList<WarehouseView>>(Array.Empty<WarehouseView>());

        public Task<IReadOnlyList<LocationView>> ListLocationsAsync(
            Guid companyId,
            Guid? warehousePublicId,
            CancellationToken cancellationToken) =>
            Task.FromResult<IReadOnlyList<LocationView>>(Array.Empty<LocationView>());

        public Task<IReadOnlyList<InventoryStockSummaryView>> ListStockAsync(
            Guid companyId,
            InventoryReadFilter filter,
            CancellationToken cancellationToken) =>
            Task.FromResult<IReadOnlyList<InventoryStockSummaryView>>(
                Array.Empty<InventoryStockSummaryView>());

        public Task<IReadOnlyList<InventoryPositionBalanceView>> ListPositionsAsync(
            Guid companyId,
            InventoryReadFilter filter,
            CancellationToken cancellationToken) =>
            Task.FromResult<IReadOnlyList<InventoryPositionBalanceView>>(
                Array.Empty<InventoryPositionBalanceView>());

        public Task<IReadOnlyList<InventoryMovementView>> ListMovementsAsync(
            Guid companyId,
            InventoryReadFilter filter,
            CancellationToken cancellationToken) =>
            Task.FromResult<IReadOnlyList<InventoryMovementView>>(
                Array.Empty<InventoryMovementView>());

        public Task<IReadOnlyList<LotTraceView>> ListLotsAsync(
            Guid companyId,
            Guid? productPublicId,
            Guid? variantPublicId,
            CancellationToken cancellationToken) =>
            Task.FromResult<IReadOnlyList<LotTraceView>>(
                Array.Empty<LotTraceView>());

        public Task<SerialTraceView?> GetSerialAsync(
            Guid companyId,
            Guid serialPublicId,
            CancellationToken cancellationToken) =>
            Task.FromResult<SerialTraceView?>(null);

        public Task<IReadOnlyList<ReservationView>> ListReservationsAsync(
            Guid companyId,
            Guid? productPublicId,
            Guid? warehousePublicId,
            CancellationToken cancellationToken) =>
            Task.FromResult<IReadOnlyList<ReservationView>>(
                Array.Empty<ReservationView>());
    }

    private sealed class FakeMutationPersistence : IInventoryMutationPersistence
    {
        public CreateWarehouseWrite? CreateWarehouseWrite { get; private set; }

        private static InventoryMutationPersistenceResult Success(Guid id) =>
            new(
                InventoryMutationOutcome.Succeeded,
                id,
                "ACTIVE",
                1);

        public Task<InventoryMutationPersistenceResult> CreateWarehouseAsync(
            CreateWarehouseWrite write,
            CancellationToken cancellationToken)
        {
            CreateWarehouseWrite = write;
            return Task.FromResult(Success(write.Warehouse.PublicId));
        }

        public Task<InventoryMutationPersistenceResult> EditWarehouseAsync(
            EditWarehouseWrite write,
            CancellationToken cancellationToken) =>
            Task.FromResult(Success(write.WarehousePublicId));

        public Task<InventoryMutationPersistenceResult> ChangeWarehouseStateAsync(
            ChangeWarehouseStateWrite write,
            CancellationToken cancellationToken) =>
            Task.FromResult(Success(write.WarehousePublicId));

        public Task<InventoryMutationPersistenceResult> CreateLocationAsync(
            CreateLocationWrite write,
            CancellationToken cancellationToken) =>
            Task.FromResult(Success(write.Location.PublicId));

        public Task<InventoryMutationPersistenceResult> EditLocationAsync(
            EditLocationWrite write,
            CancellationToken cancellationToken) =>
            Task.FromResult(Success(write.LocationPublicId));

        public Task<InventoryMutationPersistenceResult> ChangeLocationStateAsync(
            ChangeLocationStateWrite write,
            CancellationToken cancellationToken) =>
            Task.FromResult(Success(write.LocationPublicId));

        public Task<InventoryMutationPersistenceResult> CreateLotAsync(
            CreateLotWrite write,
            CancellationToken cancellationToken) =>
            Task.FromResult(Success(write.Lot.PublicId));

        public Task<InventoryMutationPersistenceResult> UpdateLotAsync(
            UpdateLotWrite write,
            CancellationToken cancellationToken) =>
            Task.FromResult(Success(write.LotPublicId));

        public Task<InventoryMutationPersistenceResult> CreateSerialAsync(
            CreateSerialWrite write,
            CancellationToken cancellationToken) =>
            Task.FromResult(Success(write.Serial.PublicId));
    }

    private sealed class FakeAuthorityPersistence : IInventoryAuthorityPersistence
    {
        public int PostCalls { get; private set; }
        public int ReservationCreateCalls { get; private set; }

        public Task<InventoryMutationPersistenceResult> PostMovementAsync(
            PostInventoryMovementWrite write,
            CancellationToken cancellationToken)
        {
            PostCalls++;
            return Task.FromResult(new InventoryMutationPersistenceResult(
                InventoryMutationOutcome.Succeeded,
                write.PublicId,
                "POSTED",
                1,
                write.Command.EnteredQuantity * write.Command.ConversionFactorSnapshot));
        }

        public Task<InventoryMutationPersistenceResult> CreateReservationAsync(
            CreateReservationWrite write,
            CancellationToken cancellationToken)
        {
            ReservationCreateCalls++;
            return Task.FromResult(new InventoryMutationPersistenceResult(
                InventoryMutationOutcome.Succeeded,
                write.PublicId,
                "ACTIVE",
                1,
                write.Command.EnteredQuantity * write.Command.ConversionFactorSnapshot));
        }

        public Task<InventoryMutationPersistenceResult> ChangeReservationAsync(
            ChangeReservationWrite write,
            CancellationToken cancellationToken) =>
            Task.FromResult(new InventoryMutationPersistenceResult(
                InventoryMutationOutcome.Succeeded,
                write.ReservationPublicId,
                "ACTIVE",
                1,
                write.Command.EnteredQuantity));
    }
}
