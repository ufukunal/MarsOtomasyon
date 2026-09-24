using Mars.Domain.Inventory;
using Mars.Domain.Warehouse;

namespace Mars.Api.Warehouse;

public sealed record WarehousePositionRequest(
    Guid WarehousePublicId,
    Guid? LocationPublicId,
    InventoryDispositionCode Disposition,
    Guid? LotPublicId,
    Guid? SerialPublicId);

public sealed record WarehouseDispositionRequest(
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal Quantity,
    decimal ConversionFactorSnapshot,
    WarehousePositionRequest Source,
    WarehousePositionRequest Target,
    string Reason,
    Guid? SourceDocumentPublicId,
    Guid? SourceLinePublicId);

public sealed record WarehouseInternalMoveRequest(
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal Quantity,
    decimal ConversionFactorSnapshot,
    WarehousePositionRequest Source,
    WarehousePositionRequest Target,
    Guid? SourceDocumentPublicId,
    Guid? SourceLinePublicId);

public sealed record WarehousePickRequest(
    Guid DispatchPublicId,
    long DispatchExpectedVersion,
    Guid DispatchLinePublicId,
    Guid WarehousePublicId,
    decimal Quantity,
    Guid LocationPublicId,
    Guid? LotPublicId,
    Guid? SerialPublicId,
    bool StrategyOverride,
    string? OverrideReason);

public sealed record WarehousePackageItemRequest(
    Guid DispatchLinePublicId,
    decimal Quantity,
    Guid? LotPublicId,
    Guid? SerialPublicId);

public sealed record CreateWarehousePackageRequest(
    string PackageCode,
    Guid DispatchPublicId,
    Guid WarehousePublicId,
    IReadOnlyList<WarehousePackageItemRequest> Items,
    string? TrackingReference,
    string? CarrierReference);

public sealed record WarehouseStageLoadRequest(
    Guid DispatchPublicId,
    Guid WarehousePublicId,
    IReadOnlyList<Guid> PackagePublicIds);

public sealed record WarehouseTransferLineRequest(
    int Sequence,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal Quantity,
    Guid? SourceLocationPublicId,
    Guid TargetLocationPublicId,
    Guid? LotPublicId,
    Guid? SerialPublicId);

public sealed record CreateWarehouseTransferRequest(
    string Number,
    Guid SourceWarehousePublicId,
    Guid TargetWarehousePublicId,
    IReadOnlyList<WarehouseTransferLineRequest> Lines);

public sealed record WarehouseVersionRequest(long Version);

public sealed record WarehouseTransferReceiveLineRequest(
    Guid TransferLinePublicId,
    decimal Quantity,
    Guid TargetLocationPublicId,
    TransferReceiptDisposition Disposition);

public sealed record WarehouseTransferReceiveRequest(
    long Version,
    IReadOnlyList<WarehouseTransferReceiveLineRequest> Lines);

public sealed record WarehouseTransferLossApprovalRequest(
    Guid TransferLinePublicId,
    decimal Quantity,
    string Reason,
    string Decision,
    string? ApprovalReason);

public sealed record WarehouseTransferLossPostRequest(
    Guid TransferLinePublicId,
    decimal Quantity,
    string Reason);

public sealed record CreateWarehouseCountRequest(
    string Number,
    Guid WarehousePublicId,
    IReadOnlyList<Guid> LocationPublicIds);

public sealed record WarehouseCountObservationRequest(
    Guid CountLinePublicId,
    decimal CountedQuantity);

public sealed record WarehouseCountObservationsRequest(
    long Version,
    bool IsRecount,
    IReadOnlyList<WarehouseCountObservationRequest> Observations);

public sealed record WarehouseApprovalRequest(
    string Decision,
    string? Reason);

public sealed record CreateWarehouseScrapRequest(
    string Number,
    Guid WarehousePublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal Quantity,
    decimal ConversionFactorSnapshot,
    WarehousePositionRequest Source,
    string Reason);

public sealed record WarehouseOfflineOperationRequest(
    Guid ClientOperationId,
    Guid WarehousePublicId,
    string OperationType,
    Guid? WorkPublicId,
    long? ExpectedVersion,
    string ScanIdentity,
    DateTimeOffset LocalTimestamp);
