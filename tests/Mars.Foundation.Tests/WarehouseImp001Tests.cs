using Mars.Application.Inventory;
using Mars.Application.Warehouse;
using Mars.Infrastructure.Persistence;
using Mars.Infrastructure.Persistence.Warehouse;
using Microsoft.EntityFrameworkCore;

internal static class WarehouseImp001Tests
{
    public static IReadOnlyList<(string Name, Action Test)> Cases { get; } =
    [
        ("WAREHOUSE-IMP-001 permission catalog preserves PLAN-006 authority", PermissionCatalogPreservesPlan),
        ("WAREHOUSE-IMP-001 model contains normalized Warehouse work tables", ModelContainsWarehouseTables),
        ("WAREHOUSE-IMP-001 model contains no mutable stock balance authority", ModelHasNoStockBalanceAuthority),
        ("WAREHOUSE-IMP-001 persistence implements Warehouse and Inventory blocker contracts", PersistenceImplementsContracts)
    ];

    private static void PermissionCatalogPreservesPlan()
    {
        AssertTrue(WarehousePermissions.All.Contains(WarehousePermissions.PickExecute));
        AssertTrue(WarehousePermissions.All.Contains(WarehousePermissions.TransferLossAdjust));
        AssertTrue(WarehousePermissions.All.Contains(WarehousePermissions.CountReverse));
        AssertTrue(WarehousePermissions.All.Contains(WarehousePermissions.ScrapPost));
        AssertTrue(!WarehousePermissions.All.Contains("warehouse.negative_stock_override"));
        AssertEqual(WarehousePermissions.All.Count, WarehousePermissions.All.Distinct(StringComparer.Ordinal).Count());
    }

    private static void ModelContainsWarehouseTables()
    {
        using var context=Create();
        var tables=context.Model.GetEntityTypes()
            .Select(x=>$"{x.GetSchema()}.{x.GetTableName()}")
            .ToHashSet(StringComparer.Ordinal);

        foreach(var table in new[]
        {
            "warehouse.operations",
            "warehouse.disposition_effects",
            "warehouse.pick_works",
            "warehouse.packages",
            "warehouse.package_items",
            "warehouse.stage_load_works",
            "warehouse.stage_load_packages",
            "warehouse.transfers",
            "warehouse.transfer_lines",
            "warehouse.transfer_effect_links",
            "warehouse.stock_count_sessions",
            "warehouse.stock_count_scopes",
            "warehouse.stock_count_lines",
            "warehouse.stock_count_observations",
            "warehouse.stock_count_effect_links",
            "warehouse.scrap_requests",
            "warehouse.offline_operations"
        }) AssertTrue(tables.Contains(table));
    }

    private static void ModelHasNoStockBalanceAuthority()
    {
        using var context=Create();
        var forbidden=new HashSet<string>(StringComparer.OrdinalIgnoreCase)
        {
            "current_stock","stock_quantity","on_hand","current_quantity","inventory_balance"
        };

        var warehouseColumns=context.Model.GetEntityTypes()
            .Where(x=>x.GetSchema()=="warehouse")
            .SelectMany(x=>x.GetProperties())
            .Select(x=>x.GetColumnName())
            .Where(x=>x is not null)
            .ToArray();

        AssertTrue(!warehouseColumns.Any(x=>forbidden.Contains(x!)));
    }

    private static void PersistenceImplementsContracts()
    {
        AssertTrue(typeof(IWarehousePersistence).IsAssignableFrom(typeof(EfWarehousePersistence)));
        AssertTrue(typeof(IWarehouseTransactionCoordinator).IsAssignableFrom(typeof(EfWarehouseTransactionCoordinator)));
        AssertTrue(typeof(IInventoryOperationalBlocker).IsAssignableFrom(typeof(EfWarehousePersistence)));
    }

    private static MarsDbContext Create()
    {
        var options=MarsDbContextOptions.CreateRuntime(
            new PostgreSqlRuntimeOptions("Host=localhost;Database=mars_warehouse_imp_001_probe"));
        return new MarsDbContext(options);
    }

    private static void AssertTrue(bool value)
    {
        if(!value)throw new InvalidOperationException("Assertion failed.");
    }

    private static void AssertEqual<T>(T expected,T actual)
    {
        if(!EqualityComparer<T>.Default.Equals(expected,actual))
            throw new InvalidOperationException($"Expected '{expected}', actual '{actual}'.");
    }
}
