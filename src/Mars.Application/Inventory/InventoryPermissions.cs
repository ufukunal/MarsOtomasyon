namespace Mars.Application.Inventory;

public static class InventoryPermissions
{
    public const string WarehouseRead = "warehouse.read";
    public const string WarehouseManage = "warehouse.manage";
    public const string WarehouseDeactivate = "warehouse.deactivate";
    public const string LocationRead = "warehouse.location.read";
    public const string LocationManage = "warehouse.location.manage";
    public const string LocationDeactivate = "warehouse.location.deactivate";
    public const string DispositionRead = "warehouse.disposition.read";
    public const string StockRead = "inventory.stock.read";
    public const string TraceRead = "inventory.trace.read";
    public const string LotManageMetadata = "inventory.lot.manage_metadata";
    public const string SerialManageMetadata = "inventory.serial.manage_metadata";
    public const string ReservationCreate = "inventory.reservation.create";
    public const string ReservationIncrease = "inventory.reservation.increase";
    public const string ReservationRelease = "inventory.reservation.release";

    public static IReadOnlyList<string> All { get; } =
    [
        WarehouseRead,
        WarehouseManage,
        WarehouseDeactivate,
        LocationRead,
        LocationManage,
        LocationDeactivate,
        DispositionRead,
        StockRead,
        TraceRead,
        LotManageMetadata,
        SerialManageMetadata
    ];
}
