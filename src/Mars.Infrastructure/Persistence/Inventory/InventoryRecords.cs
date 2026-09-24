using Mars.Domain.Inventory;

namespace Mars.Infrastructure.Persistence.Inventory;

internal sealed class WarehouseRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public string Code { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public InventoryMasterState State { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class LocationRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public long WarehouseId { get; set; }
    public long? ParentLocationId { get; set; }
    public string Code { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public bool StockBearing { get; set; }
    public InventoryMasterState State { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class InventoryDispositionRecord
{
    public long Id { get; set; }
    public InventoryDispositionCode Code { get; set; }
}

internal sealed class InventoryLotRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public string Code { get; set; } = string.Empty;
    public DateOnly? ManufactureDate { get; set; }
    public DateOnly? ExpiryDate { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class InventorySerialRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public long? LotId { get; set; }
    public string Value { get; set; } = string.Empty;
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class InventoryMovementRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public long UomId { get; set; }
    public decimal EnteredQuantity { get; set; }
    public decimal ConversionFactorSnapshot { get; set; }
    public decimal BaseQuantity { get; set; }

    public long? SourceWarehouseId { get; set; }
    public long? SourceLocationId { get; set; }
    public long? SourceDispositionId { get; set; }

    public long? TargetWarehouseId { get; set; }
    public long? TargetLocationId { get; set; }
    public long? TargetDispositionId { get; set; }

    public long? LotId { get; set; }
    public long? SerialId { get; set; }

    public string SourceModule { get; set; } = string.Empty;
    public string SourceEntityType { get; set; } = string.Empty;
    public Guid SourceDocumentPublicId { get; set; }
    public Guid? SourceLinePublicId { get; set; }

    public DateTimeOffset PostedAt { get; set; }
    public Guid ActorId { get; set; }
    public string CorrelationId { get; set; } = string.Empty;
    public long? ReversalOfMovementId { get; set; }
}

internal sealed class InventoryReservationRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public Guid SalesOrderPublicId { get; set; }
    public long SalesOrderVersion { get; set; }
    public Guid SalesOrderLinePublicId { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public long WarehouseId { get; set; }
    public long UomId { get; set; }
    public decimal ConversionFactorSnapshot { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class InventoryReservationMovementRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long ReservationId { get; set; }
    public Guid CompanyId { get; set; }
    public ReservationMovementKind Kind { get; set; }
    public decimal EnteredQuantity { get; set; }
    public decimal BaseQuantity { get; set; }
    public string? SourceModule { get; set; }
    public Guid? SourceDocumentPublicId { get; set; }
    public Guid? SourceLinePublicId { get; set; }
    public Guid ActorId { get; set; }
    public string CorrelationId { get; set; } = string.Empty;
    public DateTimeOffset OccurredAt { get; set; }
}
