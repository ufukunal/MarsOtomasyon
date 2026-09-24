using Mars.Domain.Inventory;
using Mars.Domain.Warehouse;

namespace Mars.Infrastructure.Persistence.Warehouse;

internal sealed class WarehouseOperationRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public InternalMoveKind Kind { get; set; }
    public long WarehouseId { get; set; }
    public long? SourceLocationId { get; set; }
    public long? TargetLocationId { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public long UomId { get; set; }
    public long? LotId { get; set; }
    public long? SerialId { get; set; }
    public InventoryDispositionCode Disposition { get; set; }
    public decimal Quantity { get; set; }
    public Guid InventoryMovementPublicId { get; set; }
    public Guid? SourceDocumentPublicId { get; set; }
    public Guid? SourceLinePublicId { get; set; }
    public WarehouseWorkState State { get; set; }
    public long Version { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset CompletedAt { get; set; }
}

internal sealed class WarehouseDispositionRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public long WarehouseId { get; set; }
    public long? LocationId { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public long UomId { get; set; }
    public long? LotId { get; set; }
    public long? SerialId { get; set; }
    public InventoryDispositionCode SourceDisposition { get; set; }
    public InventoryDispositionCode TargetDisposition { get; set; }
    public decimal Quantity { get; set; }
    public string Reason { get; set; } = string.Empty;
    public Guid InventoryMovementPublicId { get; set; }
    public Guid? SourceDocumentPublicId { get; set; }
    public Guid? SourceLinePublicId { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class PickWorkRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public Guid DispatchPublicId { get; set; }
    public Guid DispatchLinePublicId { get; set; }
    public long WarehouseId { get; set; }
    public long LocationId { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public long UomId { get; set; }
    public decimal ConversionFactorSnapshot { get; set; }
    public long? LotId { get; set; }
    public long? SerialId { get; set; }
    public Guid? ReservationPublicId { get; set; }
    public decimal Quantity { get; set; }
    public PickStrategy Strategy { get; set; }
    public bool StrategyOverride { get; set; }
    public string? OverrideReason { get; set; }
    public PickWorkState State { get; set; }
    public long Version { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class PackageRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public string PackageCode { get; set; } = string.Empty;
    public Guid DispatchPublicId { get; set; }
    public long WarehouseId { get; set; }
    public PackageState State { get; set; }
    public string? TrackingReference { get; set; }
    public string? CarrierReference { get; set; }
    public long Version { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class PackageItemRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long PackageId { get; set; }
    public Guid CompanyId { get; set; }
    public Guid DispatchLinePublicId { get; set; }
    public decimal Quantity { get; set; }
    public long? LotId { get; set; }
    public long? SerialId { get; set; }
}

internal sealed class StageLoadWorkRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public Guid DispatchPublicId { get; set; }
    public long WarehouseId { get; set; }
    public StageLoadKind Kind { get; set; }
    public string PackagePublicIdsSnapshot { get; set; } = string.Empty;
    public WarehouseWorkState State { get; set; }
    public long Version { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class WarehouseTransferRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public string Number { get; set; } = string.Empty;
    public long SourceWarehouseId { get; set; }
    public long TargetWarehouseId { get; set; }
    public TransferState State { get; set; }
    public long Version { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? IssuedAt { get; set; }
    public DateTimeOffset? ClosedAt { get; set; }
}

internal sealed class WarehouseTransferLineRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long TransferId { get; set; }
    public Guid CompanyId { get; set; }
    public int Sequence { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public long UomId { get; set; }
    public decimal ConversionFactorSnapshot { get; set; }
    public decimal RequestedQuantity { get; set; }
    public decimal IssuedQuantity { get; set; }
    public decimal ReceivedQuantity { get; set; }
    public decimal DamagedReceivedQuantity { get; set; }
    public decimal ResolvedLossQuantity { get; set; }
    public long? SourceLocationId { get; set; }
    public long TargetLocationId { get; set; }
    public long? LotId { get; set; }
    public long? SerialId { get; set; }
}

internal sealed class WarehouseTransferEffectRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long TransferLineId { get; set; }
    public Guid CompanyId { get; set; }
    public Guid InventoryMovementPublicId { get; set; }
    public string EffectKind { get; set; } = string.Empty;
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class StockCountSessionRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public string Number { get; set; } = string.Empty;
    public long WarehouseId { get; set; }
    public StockCountState State { get; set; }
    public long Version { get; set; }
    public long? SnapshotMovementId { get; set; }
    public Guid CreatorActorId { get; set; }
    public Guid? ApprovalDecisionPublicId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? StartedAt { get; set; }
    public DateTimeOffset? ReviewedAt { get; set; }
    public DateTimeOffset? PostedAt { get; set; }
}

internal sealed class StockCountScopeRecord
{
    public long Id { get; set; }
    public long CountSessionId { get; set; }
    public Guid CompanyId { get; set; }
    public long LocationId { get; set; }
}

internal sealed class StockCountLineRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long CountSessionId { get; set; }
    public Guid CompanyId { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public long UomId { get; set; }
    public decimal ConversionFactorSnapshot { get; set; }
    public long LocationId { get; set; }
    public long DispositionId { get; set; }
    public long? LotId { get; set; }
    public long? SerialId { get; set; }
    public decimal ExpectedStartQuantity { get; set; }
    public decimal NetInterveningQuantity { get; set; }
    public decimal ExpectedReconciliationQuantity { get; set; }
    public decimal? AcceptedCountQuantity { get; set; }
    public decimal DiscrepancyQuantity { get; set; }
}

internal sealed class StockCountObservationRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long CountLineId { get; set; }
    public Guid CompanyId { get; set; }
    public decimal CountedQuantity { get; set; }
    public bool IsRecount { get; set; }
    public Guid ActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class StockCountEffectRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long CountLineId { get; set; }
    public Guid CompanyId { get; set; }
    public Guid InventoryMovementPublicId { get; set; }
    public bool IsReversal { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class WarehouseScrapRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public string Number { get; set; } = string.Empty;
    public long WarehouseId { get; set; }
    public long? LocationId { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public long UomId { get; set; }
    public decimal ConversionFactorSnapshot { get; set; }
    public long DispositionId { get; set; }
    public long? LotId { get; set; }
    public long? SerialId { get; set; }
    public decimal Quantity { get; set; }
    public string Reason { get; set; } = string.Empty;
    public ScrapState State { get; set; }
    public long Version { get; set; }
    public Guid CreatorActorId { get; set; }
    public Guid? ApprovalDecisionPublicId { get; set; }
    public Guid? InventoryMovementPublicId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? PostedAt { get; set; }
}

internal sealed class WarehouseOfflineOperationRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public Guid ClientOperationId { get; set; }
    public long WarehouseId { get; set; }
    public string OperationType { get; set; } = string.Empty;
    public Guid? WorkPublicId { get; set; }
    public long? ExpectedVersion { get; set; }
    public string ScanIdentity { get; set; } = string.Empty;
    public DateTimeOffset LocalTimestamp { get; set; }
    public OfflineOperationState State { get; set; }
    public string? ConflictCode { get; set; }
    public Guid? ServerResultPublicId { get; set; }
    public Guid ActorId { get; set; }
    public string CorrelationId { get; set; } = string.Empty;
    public DateTimeOffset CreatedAt { get; set; }
}
