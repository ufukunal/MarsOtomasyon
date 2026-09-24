namespace Mars.Api.Inventory;

public sealed record CreateWarehouseRequest(string Code, string Name);
public sealed record EditWarehouseRequest(long Version, string Code, string Name);
public sealed record ChangeInventoryMasterStateRequest(long Version, string State);

public sealed record CreateLocationRequest(
    Guid WarehousePublicId,
    Guid? ParentLocationPublicId,
    string Code,
    string Name,
    bool StockBearing);

public sealed record EditLocationRequest(
    long Version,
    Guid? ParentLocationPublicId,
    string Code,
    string Name,
    bool StockBearing);

public sealed record CreateInventoryLotRequest(
    Guid ProductPublicId,
    Guid? VariantPublicId,
    string Code,
    DateOnly? ManufactureDate,
    DateOnly? ExpiryDate);

public sealed record UpdateInventoryLotRequest(
    long Version,
    DateOnly? ManufactureDate,
    DateOnly? ExpiryDate);

public sealed record CreateInventorySerialRequest(
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid? LotPublicId,
    string Value);

public sealed record WarehouseAccessGrantRequest(Guid ActorId);
