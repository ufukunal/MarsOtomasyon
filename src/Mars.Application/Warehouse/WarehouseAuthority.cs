using Mars.Application.Foundation.Approvals;
using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Inventory;
using Mars.Domain.Inventory;
using Mars.Domain.Warehouse;

namespace Mars.Application.Warehouse;

public sealed record WarehouseMutationReceipt(
    Guid PublicId,
    string State,
    long Version,
    string CorrelationId);

public sealed record ReceivingQueueItem(
    Guid GoodsReceiptPublicId,
    string GoodsReceiptNumber,
    Guid PurchaseOrderPublicId,
    Guid GoodsReceiptLinePublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    Guid WarehousePublicId,
    Guid? LocationPublicId,
    Guid? LotPublicId,
    Guid? SerialPublicId,
    decimal Quantity,
    decimal RemainingQuarantineQuantity,
    DateTimeOffset PostedAt);

public sealed record WarehouseWorkListItem(
    Guid PublicId,
    string Number,
    string Kind,
    string State,
    Guid WarehousePublicId,
    long Version,
    DateTimeOffset CreatedAt);

public sealed record PickCandidateView(
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    Guid WarehousePublicId,
    Guid? LocationPublicId,
    Guid? LotPublicId,
    Guid? SerialPublicId,
    decimal EligibleQuantity,
    DateOnly? ExpiryDate,
    DateTimeOffset FirstAvailableAt,
    PickStrategy Strategy,
    int Rank);

public sealed record TransferLineInput(
    int Sequence,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal Quantity,
    Guid? SourceLocationPublicId,
    Guid TargetLocationPublicId,
    Guid? LotPublicId,
    Guid? SerialPublicId);

public sealed record CreateTransferCommand(
    string Number,
    Guid SourceWarehousePublicId,
    Guid TargetWarehousePublicId,
    IReadOnlyList<TransferLineInput> Lines,
    string OperationKey);

public sealed record TransferIssueLinePlan(
    Guid TransferLinePublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal ConversionFactorSnapshot,
    decimal Quantity,
    InventoryPosition Source,
    InventoryPosition TransitTarget);

public sealed record TransferIssuePlan(
    Guid TransferPublicId,
    Guid SourceWarehousePublicId,
    Guid TargetWarehousePublicId,
    IReadOnlyList<TransferIssueLinePlan> Lines);

public sealed record TransferReceiveLineInput(
    Guid TransferLinePublicId,
    decimal Quantity,
    Guid TargetLocationPublicId,
    TransferReceiptDisposition Disposition);

public sealed record TransferReceiveCommand(
    Guid TransferPublicId,
    long ExpectedVersion,
    IReadOnlyList<TransferReceiveLineInput> Lines,
    string OperationKey);

public sealed record TransferReceiveLinePlan(
    Guid TransferLinePublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal ConversionFactorSnapshot,
    decimal Quantity,
    InventoryPosition TransitSource,
    InventoryPosition Target);

public sealed record TransferReceivePlan(
    Guid TransferPublicId,
    Guid TargetWarehousePublicId,
    IReadOnlyList<TransferReceiveLinePlan> Lines);

public sealed record WarehouseInventoryEffect(
    Guid WorkLinePublicId,
    Guid InventoryMovementPublicId,
    Guid? OriginalInventoryMovementPublicId,
    bool IsReversal);

public sealed record ChangeDispositionCommand(
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal Quantity,
    decimal ConversionFactorSnapshot,
    InventoryPosition Source,
    InventoryPosition Target,
    string Reason,
    Guid? SourceDocumentPublicId,
    Guid? SourceLinePublicId,
    string OperationKey);

public sealed record InternalMoveCommand(
    InternalMoveKind Kind,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal Quantity,
    decimal ConversionFactorSnapshot,
    InventoryPosition Source,
    InventoryPosition Target,
    Guid? SourceDocumentPublicId,
    Guid? SourceLinePublicId,
    string OperationKey);

public sealed record RecordPickCommand(
    Guid DispatchPublicId,
    long DispatchExpectedVersion,
    Guid DispatchLinePublicId,
    Guid WarehousePublicId,
    decimal Quantity,
    Guid LocationPublicId,
    Guid? LotPublicId,
    Guid? SerialPublicId,
    bool StrategyOverride,
    string? OverrideReason,
    string OperationKey);

public sealed record PickPlan(
    Guid PickWorkPublicId,
    Guid DispatchPublicId,
    Guid DispatchLinePublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    Guid WarehousePublicId,
    decimal Quantity,
    decimal ConversionFactorSnapshot,
    Guid LocationPublicId,
    Guid? LotPublicId,
    Guid? SerialPublicId,
    Guid? ReservationPublicId,
    bool StrategyOverride,
    string? OverrideReason);

public sealed record BindDispatchSourceCommand(
    Guid DispatchPublicId,
    long ExpectedVersion,
    Guid DispatchLinePublicId,
    Guid WarehousePublicId,
    decimal PickedQuantity,
    Guid LocationPublicId,
    Guid? LotPublicId,
    Guid? SerialPublicId,
    string OperationKey);

public interface ISalesWarehouseDispatchAuthority
{
    Task<Result<WarehouseMutationReceipt>> BindSourceAsync(
        BindDispatchSourceCommand command,
        IExecutionContext context,
        CancellationToken cancellationToken);
}

public sealed record PackageItemInput(
    Guid DispatchLinePublicId,
    decimal Quantity,
    Guid? LotPublicId,
    Guid? SerialPublicId);

public sealed record CreatePackageCommand(
    string PackageCode,
    Guid DispatchPublicId,
    Guid WarehousePublicId,
    IReadOnlyList<PackageItemInput> Items,
    string? TrackingReference,
    string? CarrierReference,
    string OperationKey);

public sealed record StageLoadCommand(
    Guid DispatchPublicId,
    Guid WarehousePublicId,
    StageLoadKind Kind,
    IReadOnlyList<Guid> PackagePublicIds,
    string OperationKey);

public sealed record CreateCountCommand(
    string Number,
    Guid WarehousePublicId,
    IReadOnlyList<Guid> LocationPublicIds,
    string OperationKey);

public sealed record CountObservationInput(
    Guid CountLinePublicId,
    decimal CountedQuantity);

public sealed record RecordCountObservationCommand(
    Guid CountPublicId,
    long ExpectedVersion,
    IReadOnlyList<CountObservationInput> Observations,
    bool IsRecount,
    string OperationKey);

public sealed record CountAdjustmentPlanLine(
    Guid CountLinePublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal ConversionFactorSnapshot,
    decimal DiscrepancyQuantity,
    InventoryPosition Position);

public sealed record CountAdjustmentPlan(
    Guid CountPublicId,
    Guid WarehousePublicId,
    Guid CreatorActorId,
    long SnapshotVersion,
    IReadOnlyList<CountAdjustmentPlanLine> Lines);

public sealed record ScrapRequestCommand(
    string Number,
    Guid WarehousePublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal Quantity,
    decimal ConversionFactorSnapshot,
    InventoryPosition Source,
    string Reason,
    string OperationKey);

public sealed record ScrapPostPlan(
    Guid ScrapPublicId,
    Guid WarehousePublicId,
    Guid CreatorActorId,
    long SnapshotVersion,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal Quantity,
    decimal ConversionFactorSnapshot,
    InventoryPosition Source);

public sealed record OfflineOperationCommand(
    Guid ClientOperationId,
    Guid WarehousePublicId,
    string OperationType,
    Guid? WorkPublicId,
    long? ExpectedVersion,
    string ScanIdentity,
    DateTimeOffset LocalTimestamp,
    string OperationKey);

public sealed record OfflineOperationView(
    Guid PublicId,
    Guid ClientOperationId,
    Guid WarehousePublicId,
    string OperationType,
    string State,
    string? ConflictCode,
    Guid? ServerResultPublicId,
    DateTimeOffset LocalTimestamp,
    DateTimeOffset CreatedAt);

public interface IWarehouseTransactionCoordinator
{
    Task<Result<T>> ExecuteAsync<T>(
        Func<CancellationToken,Task<Result<T>>> operation,
        CancellationToken cancellationToken);
}

public interface IWarehouseOpenWorkBlocker
{
    Task<bool> HasOpenWarehouseWorkAsync(Guid companyId,Guid warehousePublicId,CancellationToken ct);
    Task<bool> HasOpenLocationWorkAsync(Guid companyId,Guid locationPublicId,CancellationToken ct);
}

public interface IWarehousePersistence : IWarehouseOpenWorkBlocker
{
    Task<IReadOnlyList<ReceivingQueueItem>> ListReceivingAsync(Guid companyId,CancellationToken ct);
    Task<IReadOnlyList<WarehouseWorkListItem>> ListPutAwayAsync(Guid companyId,CancellationToken ct);
    Task<IReadOnlyList<WarehouseWorkListItem>> ListPicksAsync(Guid companyId,CancellationToken ct);
    Task<IReadOnlyList<WarehouseWorkListItem>> ListTransfersAsync(Guid companyId,CancellationToken ct);
    Task<IReadOnlyList<WarehouseWorkListItem>> ListCountsAsync(Guid companyId,CancellationToken ct);
    Task<IReadOnlyList<WarehouseWorkListItem>> ListScrapAsync(Guid companyId,CancellationToken ct);
    Task<IReadOnlyList<OfflineOperationView>> ListOfflineAsync(Guid companyId,CancellationToken ct);

    Task<Result<WarehouseMutationReceipt>> CompleteDispositionAsync(
        ChangeDispositionCommand command,Guid inventoryMovementPublicId,IExecutionContext context,CancellationToken ct);
    Task<Result<WarehouseMutationReceipt>> CompleteInternalMoveAsync(
        InternalMoveCommand command,Guid inventoryMovementPublicId,IExecutionContext context,CancellationToken ct);

    Task<Result<PickPlan>> PreparePickAsync(RecordPickCommand command,IExecutionContext context,CancellationToken ct);
    Task<Result<WarehouseMutationReceipt>> CompletePickAsync(
        PickPlan plan,IExecutionContext context,CancellationToken ct);
    Task<IReadOnlyList<PickCandidateView>> RecommendPickAsync(
        Guid companyId,Guid dispatchPublicId,Guid dispatchLinePublicId,CancellationToken ct);

    Task<Result<WarehouseMutationReceipt>> CreatePackageAsync(
        CreatePackageCommand command,IExecutionContext context,CancellationToken ct);
    Task<Result<WarehouseMutationReceipt>> RecordStageLoadAsync(
        StageLoadCommand command,IExecutionContext context,CancellationToken ct);

    Task<Result<WarehouseMutationReceipt>> CreateTransferAsync(
        CreateTransferCommand command,IExecutionContext context,CancellationToken ct);
    Task<Guid?> GetTransferWarehouseAsync(Guid companyId,Guid transferPublicId,bool target,CancellationToken ct);
    Task<Result<TransferIssuePlan>> PrepareTransferIssueAsync(
        Guid transferPublicId,long expectedVersion,IExecutionContext context,CancellationToken ct);
    Task<Result<WarehouseMutationReceipt>> CompleteTransferIssueAsync(
        Guid transferPublicId,IReadOnlyList<WarehouseInventoryEffect> effects,IExecutionContext context,CancellationToken ct);
    Task<Result<TransferReceivePlan>> PrepareTransferReceiveAsync(
        TransferReceiveCommand command,IExecutionContext context,CancellationToken ct);
    Task<Result<WarehouseMutationReceipt>> CompleteTransferReceiveAsync(
        Guid transferPublicId,IReadOnlyList<WarehouseInventoryEffect> effects,IExecutionContext context,CancellationToken ct);
    Task<Result<ScrapPostPlan>> PrepareTransferLossAsync(
        Guid transferPublicId,Guid transferLinePublicId,decimal quantity,string reason,IExecutionContext context,CancellationToken ct);
    Task<Result<WarehouseMutationReceipt>> CompleteTransferLossAsync(
        Guid transferPublicId,Guid transferLinePublicId,decimal quantity,Guid movementPublicId,IExecutionContext context,CancellationToken ct);

    Task<Result<WarehouseMutationReceipt>> CreateCountAsync(
        CreateCountCommand command,IExecutionContext context,CancellationToken ct);
    Task<Result<WarehouseMutationReceipt>> StartCountAsync(
        Guid countPublicId,long expectedVersion,string operationKey,IExecutionContext context,CancellationToken ct);
    Task<Result<WarehouseMutationReceipt>> RecordCountAsync(
        RecordCountObservationCommand command,IExecutionContext context,CancellationToken ct);
    Task<Result<CountAdjustmentPlan>> ReviewCountAsync(
        Guid countPublicId,long expectedVersion,string operationKey,IExecutionContext context,CancellationToken ct);
    Task<Result<CountAdjustmentPlan>> GetCountPostPlanAsync(
        Guid countPublicId,IExecutionContext context,CancellationToken ct);
    Task<Result<WarehouseMutationReceipt>> MarkCountApprovalAsync(
        Guid countPublicId,Guid approvalPublicId,IExecutionContext context,CancellationToken ct);
    Task<Result<WarehouseMutationReceipt>> CompleteCountPostAsync(
        Guid countPublicId,IReadOnlyList<WarehouseInventoryEffect> effects,IExecutionContext context,CancellationToken ct);

    Task<Result<WarehouseMutationReceipt>> RequestScrapAsync(
        ScrapRequestCommand command,IExecutionContext context,CancellationToken ct);
    Task<Result<ScrapPostPlan>> GetScrapPostPlanAsync(
        Guid scrapPublicId,IExecutionContext context,CancellationToken ct);
    Task<Result<WarehouseMutationReceipt>> MarkScrapApprovalAsync(
        Guid scrapPublicId,Guid approvalPublicId,IExecutionContext context,CancellationToken ct);
    Task<Result<WarehouseMutationReceipt>> CompleteScrapPostAsync(
        Guid scrapPublicId,Guid movementPublicId,IExecutionContext context,CancellationToken ct);

    Task<Result<WarehouseMutationReceipt>> RecordOfflineAsync(
        OfflineOperationCommand command,IExecutionContext context,CancellationToken ct);
}

public sealed class WarehouseQueryHandler(
    IPermissionEvaluator permissions,
    IWarehousePersistence persistence)
{
    public Task<Result<IReadOnlyList<ReceivingQueueItem>>> ListReceivingAsync(IExecutionContext c,CancellationToken ct)=>
        Read(WarehousePermissions.ReceivingRead,()=>persistence.ListReceivingAsync(c.CompanyId,ct),c,ct);
    public Task<Result<IReadOnlyList<WarehouseWorkListItem>>> ListPutAwayAsync(IExecutionContext c,CancellationToken ct)=>
        Read(WarehousePermissions.DispositionRead,()=>persistence.ListPutAwayAsync(c.CompanyId,ct),c,ct);
    public Task<Result<IReadOnlyList<WarehouseWorkListItem>>> ListPicksAsync(IExecutionContext c,CancellationToken ct)=>
        Read(WarehousePermissions.PickRead,()=>persistence.ListPicksAsync(c.CompanyId,ct),c,ct);
    public Task<Result<IReadOnlyList<WarehouseWorkListItem>>> ListTransfersAsync(IExecutionContext c,CancellationToken ct)=>
        Read(WarehousePermissions.TransferRead,()=>persistence.ListTransfersAsync(c.CompanyId,ct),c,ct);
    public Task<Result<IReadOnlyList<WarehouseWorkListItem>>> ListCountsAsync(IExecutionContext c,CancellationToken ct)=>
        Read(WarehousePermissions.CountRead,()=>persistence.ListCountsAsync(c.CompanyId,ct),c,ct);
    public Task<Result<IReadOnlyList<WarehouseWorkListItem>>> ListScrapAsync(IExecutionContext c,CancellationToken ct)=>
        Read(WarehousePermissions.TraceRead,()=>persistence.ListScrapAsync(c.CompanyId,ct),c,ct);
    public Task<Result<IReadOnlyList<OfflineOperationView>>> ListOfflineAsync(IExecutionContext c,CancellationToken ct)=>
        Read(WarehousePermissions.TraceRead,()=>persistence.ListOfflineAsync(c.CompanyId,ct),c,ct);

    public async Task<Result<IReadOnlyList<PickCandidateView>>> RecommendPickAsync(
        Guid dispatchPublicId,Guid dispatchLinePublicId,IExecutionContext c,CancellationToken ct)
    {
        if(!await Granted(WarehousePermissions.PickRead,c,ct)) return Denied<IReadOnlyList<PickCandidateView>>();
        return Result<IReadOnlyList<PickCandidateView>>.Success(
            await persistence.RecommendPickAsync(c.CompanyId,dispatchPublicId,dispatchLinePublicId,ct));
    }

    private async Task<Result<IReadOnlyList<T>>> Read<T>(
        string permission,Func<Task<IReadOnlyList<T>>> read,IExecutionContext c,CancellationToken ct)
    {
        if(!await Granted(permission,c,ct)) return Denied<IReadOnlyList<T>>();
        return Result<IReadOnlyList<T>>.Success(await read());
    }

    private Task<bool> Granted(string p,IExecutionContext c,CancellationToken ct)=>
        permissions.IsGrantedAsync(c.ActorId,c.CompanyId,p,ct);
    private static Result<T> Denied<T>()=>Result<T>.Failure(
        new ApplicationError(ErrorCategory.Authorization,"authorization.permission_denied","Warehouse permission denied."));
}

public sealed class WarehouseCommandHandler(
    IPermissionEvaluator permissions,
    IWarehouseAccessEvaluator warehouseAccess,
    IInventoryPhysicalAuthority inventory,
    ISalesWarehouseDispatchAuthority salesDispatch,
    IApprovalDecisionAuthority approvals,
    IWarehousePersistence persistence,
    IWarehouseTransactionCoordinator transactions)
{
    public async Task<Result<WarehouseMutationReceipt>> ChangeDispositionAsync(
        ChangeDispositionCommand command,IExecutionContext c,CancellationToken ct)
    {
        var permission=command.Target.Disposition==InventoryDispositionCode.Available
            ? WarehousePermissions.DispositionRelease
            : WarehousePermissions.DispositionChange;
        if(!await Authorized(permission,command.Source.WarehousePublicId,c,ct)) return Denied();
        if(command.Source.WarehousePublicId!=command.Target.WarehousePublicId||
           command.Source.LocationPublicId!=command.Target.LocationPublicId||
           command.Source.LotPublicId!=command.Target.LotPublicId||
           command.Source.SerialPublicId!=command.Target.SerialPublicId||
           command.Source.Disposition==command.Target.Disposition||
           command.Source.Disposition==InventoryDispositionCode.Transit||
           command.Target.Disposition==InventoryDispositionCode.Transit)
            return Invalid("warehouse.disposition.invalid","Disposition change must preserve physical identity/location and cannot manually use TRANSIT.");

        return await transactions.ExecuteAsync(async innerCt=>{
            var movement=await inventory.PostAsync(new InventoryMovementCommand(
                command.ProductPublicId,command.VariantPublicId,command.UomPublicId,command.Quantity,
                command.ConversionFactorSnapshot,command.Source,command.Target,
                InventorySourceIdentity.Create("Warehouse","Disposition",command.SourceDocumentPublicId??Guid.NewGuid(),command.SourceLinePublicId),
                null,command.OperationKey+".inventory"),c,innerCt);
            if(movement.IsFailure)return Result<WarehouseMutationReceipt>.Failure(movement.Error!);
            return await persistence.CompleteDispositionAsync(command,movement.Value!.MovementPublicId,c,innerCt);
        },ct);
    }

    public async Task<Result<WarehouseMutationReceipt>> ExecuteInternalMoveAsync(
        InternalMoveCommand command,IExecutionContext c,CancellationToken ct)
    {
        if(!await Authorized(WarehousePermissions.PutAwayExecute,command.Source.WarehousePublicId,c,ct)) return Denied();
        if(command.Source.WarehousePublicId!=command.Target.WarehousePublicId||
           command.Source.Disposition!=command.Target.Disposition||
           command.Source.LocationPublicId==command.Target.LocationPublicId)
            return Invalid("warehouse.move.invalid","Internal movement must stay in one Warehouse, preserve disposition and change Location.");
        if(command.Kind==InternalMoveKind.Replenishment&&command.Source.Disposition!=InventoryDispositionCode.Available)
            return Invalid("warehouse.replenishment.available_required","Replenishment source must be AVAILABLE.");

        return await transactions.ExecuteAsync(async innerCt=>{
            var movement=await inventory.PostAsync(new InventoryMovementCommand(
                command.ProductPublicId,command.VariantPublicId,command.UomPublicId,command.Quantity,
                command.ConversionFactorSnapshot,command.Source,command.Target,
                InventorySourceIdentity.Create("Warehouse",command.Kind.ToString(),command.SourceDocumentPublicId??Guid.NewGuid(),command.SourceLinePublicId),
                null,command.OperationKey+".inventory"),c,innerCt);
            if(movement.IsFailure)return Result<WarehouseMutationReceipt>.Failure(movement.Error!);
            return await persistence.CompleteInternalMoveAsync(command,movement.Value!.MovementPublicId,c,innerCt);
        },ct);
    }

    public async Task<Result<WarehouseMutationReceipt>> RecordPickAsync(
        RecordPickCommand command,IExecutionContext c,CancellationToken ct)
    {
        if(!await Authorized(WarehousePermissions.PickExecute,command.WarehousePublicId,c,ct))return Denied();
        if(command.StrategyOverride){
            if(!await Granted(WarehousePermissions.PickStrategyOverride,c,ct))return Denied();
            if(string.IsNullOrWhiteSpace(command.OverrideReason))
                return Invalid("warehouse.pick.override_reason","Strategy override requires a reason.");
        }
        return await transactions.ExecuteAsync(async innerCt=>{
            var plan=await persistence.PreparePickAsync(command,c,innerCt);
            if(plan.IsFailure)return Result<WarehouseMutationReceipt>.Failure(plan.Error!);
            var p=plan.Value!;
            var bind=await salesDispatch.BindSourceAsync(new BindDispatchSourceCommand(
                p.DispatchPublicId,command.DispatchExpectedVersion,p.DispatchLinePublicId,p.WarehousePublicId,
                p.Quantity,p.LocationPublicId,p.LotPublicId,p.SerialPublicId,command.OperationKey+".sales-bind"),c,innerCt);
            if(bind.IsFailure)return bind;
            return await persistence.CompletePickAsync(p,c,innerCt);
        },ct);
    }

    public async Task<Result<WarehouseMutationReceipt>> CreatePackageAsync(
        CreatePackageCommand command,IExecutionContext c,CancellationToken ct)
    {
        if(!await Authorized(WarehousePermissions.PackExecute,command.WarehousePublicId,c,ct))return Denied();
        return await persistence.CreatePackageAsync(command,c,ct);
    }

    public async Task<Result<WarehouseMutationReceipt>> StageLoadAsync(
        StageLoadCommand command,IExecutionContext c,CancellationToken ct)
    {
        var p=command.Kind==StageLoadKind.Stage?WarehousePermissions.StageExecute:WarehousePermissions.LoadExecute;
        if(!await Authorized(p,command.WarehousePublicId,c,ct))return Denied();
        return await persistence.RecordStageLoadAsync(command,c,ct);
    }

    public async Task<Result<WarehouseMutationReceipt>> CreateTransferAsync(
        CreateTransferCommand command,IExecutionContext c,CancellationToken ct)
    {
        if(!await Granted(WarehousePermissions.TransferCreate,c,ct))return Denied();
        if(command.SourceWarehousePublicId==command.TargetWarehousePublicId)
            return Invalid("warehouse.transfer.same_warehouse","Transfer Warehouses must differ.");
        if(!await warehouseAccess.IsGrantedAsync(c.ActorId,c.CompanyId,command.SourceWarehousePublicId,ct)||
           !await warehouseAccess.IsGrantedAsync(c.ActorId,c.CompanyId,command.TargetWarehousePublicId,ct))return Denied();
        return await persistence.CreateTransferAsync(command,c,ct);
    }

    public async Task<Result<WarehouseMutationReceipt>> IssueTransferAsync(
        Guid transferPublicId,long expectedVersion,string operationKey,IExecutionContext c,CancellationToken ct)
    {
        if(!await Granted(WarehousePermissions.TransferIssue,c,ct))return Denied();
        var warehouse=await persistence.GetTransferWarehouseAsync(c.CompanyId,transferPublicId,false,ct);
        if(!warehouse.HasValue||!await warehouseAccess.IsGrantedAsync(c.ActorId,c.CompanyId,warehouse.Value,ct))return Denied();
        return await transactions.ExecuteAsync(async innerCt=>{
            var plan=await persistence.PrepareTransferIssueAsync(transferPublicId,expectedVersion,c,innerCt);
            if(plan.IsFailure)return Result<WarehouseMutationReceipt>.Failure(plan.Error!);
            var effects=new List<WarehouseInventoryEffect>();
            foreach(var line in plan.Value!.Lines){
                var m=await inventory.PostAsync(new InventoryMovementCommand(
                    line.ProductPublicId,line.VariantPublicId,line.UomPublicId,line.Quantity,line.ConversionFactorSnapshot,
                    line.Source,line.TransitTarget,
                    InventorySourceIdentity.Create("Warehouse","TransferIssue",plan.Value.TransferPublicId,line.TransferLinePublicId),
                    null,$"{operationKey}:issue:{line.TransferLinePublicId:D}"),c,innerCt);
                if(m.IsFailure)return Result<WarehouseMutationReceipt>.Failure(m.Error!);
                effects.Add(new(line.TransferLinePublicId,m.Value!.MovementPublicId,null,false));
            }
            return await persistence.CompleteTransferIssueAsync(transferPublicId,effects,c,innerCt);
        },ct);
    }

    public async Task<Result<WarehouseMutationReceipt>> ReceiveTransferAsync(
        TransferReceiveCommand command,IExecutionContext c,CancellationToken ct)
    {
        if(!await Granted(WarehousePermissions.TransferReceive,c,ct))return Denied();
        var warehouse=await persistence.GetTransferWarehouseAsync(c.CompanyId,command.TransferPublicId,true,ct);
        if(!warehouse.HasValue||!await warehouseAccess.IsGrantedAsync(c.ActorId,c.CompanyId,warehouse.Value,ct))return Denied();
        return await transactions.ExecuteAsync(async innerCt=>{
            var plan=await persistence.PrepareTransferReceiveAsync(command,c,innerCt);
            if(plan.IsFailure)return Result<WarehouseMutationReceipt>.Failure(plan.Error!);
            var effects=new List<WarehouseInventoryEffect>();
            foreach(var line in plan.Value!.Lines){
                var m=await inventory.PostAsync(new InventoryMovementCommand(
                    line.ProductPublicId,line.VariantPublicId,line.UomPublicId,line.Quantity,line.ConversionFactorSnapshot,
                    line.TransitSource,line.Target,
                    InventorySourceIdentity.Create("Warehouse","TransferReceive",plan.Value.TransferPublicId,line.TransferLinePublicId),
                    null,$"{command.OperationKey}:receive:{line.TransferLinePublicId:D}"),c,innerCt);
                if(m.IsFailure)return Result<WarehouseMutationReceipt>.Failure(m.Error!);
                effects.Add(new(line.TransferLinePublicId,m.Value!.MovementPublicId,null,false));
            }
            return await persistence.CompleteTransferReceiveAsync(command.TransferPublicId,effects,c,innerCt);
        },ct);
    }

    public async Task<Result<WarehouseMutationReceipt>> CreateCountAsync(
        CreateCountCommand command,IExecutionContext c,CancellationToken ct)
    {
        if(!await Authorized(WarehousePermissions.CountCreate,command.WarehousePublicId,c,ct))return Denied();
        return await persistence.CreateCountAsync(command,c,ct);
    }

    public async Task<Result<WarehouseMutationReceipt>> StartCountAsync(
        Guid id,long version,string operationKey,Guid warehousePublicId,IExecutionContext c,CancellationToken ct)
    {
        if(!await Authorized(WarehousePermissions.CountExecute,warehousePublicId,c,ct))return Denied();
        return await persistence.StartCountAsync(id,version,operationKey,c,ct);
    }

    public async Task<Result<WarehouseMutationReceipt>> RecordCountAsync(
        RecordCountObservationCommand command,Guid warehousePublicId,IExecutionContext c,CancellationToken ct)
    {
        if(!await Authorized(WarehousePermissions.CountExecute,warehousePublicId,c,ct))return Denied();
        return await persistence.RecordCountAsync(command,c,ct);
    }

    public async Task<Result<WarehouseMutationReceipt>> ReviewCountAsync(
        Guid id,long version,string operationKey,Guid warehousePublicId,IExecutionContext c,CancellationToken ct)
    {
        if(!await Authorized(WarehousePermissions.CountReview,warehousePublicId,c,ct))return Denied();
        var plan=await persistence.ReviewCountAsync(id,version,operationKey,c,ct);
        if(plan.IsFailure)return Result<WarehouseMutationReceipt>.Failure(plan.Error!);
        return Result<WarehouseMutationReceipt>.Success(new(id,
            plan.Value!.Lines.Any(x=>x.DiscrepancyQuantity!=0m)?"PENDING_APPROVAL":"CLOSED",
            plan.Value.SnapshotVersion,c.CorrelationId.Value));
    }

    public async Task<Result<ApprovalDecisionReceipt>> ApproveCountAsync(
        Guid id,ApprovalDecisionKind decision,string? reason,string operationKey,Guid warehousePublicId,IExecutionContext c,CancellationToken ct)
    {
        if(!await Authorized(WarehousePermissions.CountApprove,warehousePublicId,c,ct))
            return Result<ApprovalDecisionReceipt>.Failure(DeniedError());
        var plan=await persistence.GetCountPostPlanAsync(id,c,ct);
        if(plan.IsFailure)return Result<ApprovalDecisionReceipt>.Failure(plan.Error!);
        var approval=await approvals.DecideAsync(new ApprovalDecisionCommand(
            "Warehouse","StockCount",id,plan.Value!.SnapshotVersion,plan.Value.CreatorActorId,decision,reason,operationKey),c,ct);
        if(approval.IsFailure)return approval;
        if(decision==ApprovalDecisionKind.Approved){
            var marked=await persistence.MarkCountApprovalAsync(id,approval.Value!.PublicId,c,ct);
            if(marked.IsFailure)return Result<ApprovalDecisionReceipt>.Failure(marked.Error!);
        }
        return approval;
    }

    public async Task<Result<WarehouseMutationReceipt>> PostCountAsync(
        Guid id,string operationKey,Guid warehousePublicId,IExecutionContext c,CancellationToken ct)
    {
        if(!await Authorized(WarehousePermissions.CountPost,warehousePublicId,c,ct))return Denied();
        return await transactions.ExecuteAsync(async innerCt=>{
            var plan=await persistence.GetCountPostPlanAsync(id,c,innerCt);
            if(plan.IsFailure)return Result<WarehouseMutationReceipt>.Failure(plan.Error!);
            if(plan.Value!.Lines.Any(x=>x.DiscrepancyQuantity>0m))
                return Invalid("warehouse.count.positive_valuation_required",
                    "Positive count adjustment requires implemented Finance/Costing valuation authority.");
            var effects=new List<WarehouseInventoryEffect>();
            foreach(var line in plan.Value.Lines.Where(x=>x.DiscrepancyQuantity<0m)){
                var q=Math.Abs(line.DiscrepancyQuantity);
                var m=await inventory.PostAsync(new InventoryMovementCommand(
                    line.ProductPublicId,line.VariantPublicId,line.UomPublicId,q,line.ConversionFactorSnapshot,
                    line.Position,null,InventorySourceIdentity.Create("Warehouse","StockCount",id,line.CountLinePublicId),
                    null,$"{operationKey}:count:{line.CountLinePublicId:D}"),c,innerCt);
                if(m.IsFailure)return Result<WarehouseMutationReceipt>.Failure(m.Error!);
                effects.Add(new(line.CountLinePublicId,m.Value!.MovementPublicId,null,false));
            }
            return await persistence.CompleteCountPostAsync(id,effects,c,innerCt);
        },ct);
    }

    public async Task<Result<WarehouseMutationReceipt>> RequestScrapAsync(
        ScrapRequestCommand command,IExecutionContext c,CancellationToken ct)
    {
        if(!await Authorized(WarehousePermissions.ScrapRequest,command.WarehousePublicId,c,ct))return Denied();
        if(command.Source.Disposition is not (InventoryDispositionCode.Damaged or InventoryDispositionCode.Rework or InventoryDispositionCode.QualityHold))
            return Invalid("warehouse.scrap.source","Scrap source must be DAMAGED, REWORK or QUALITY_HOLD.");
        return await persistence.RequestScrapAsync(command,c,ct);
    }

    public async Task<Result<ApprovalDecisionReceipt>> ApproveScrapAsync(
        Guid id,ApprovalDecisionKind decision,string? reason,string operationKey,IExecutionContext c,CancellationToken ct)
    {
        var plan=await persistence.GetScrapPostPlanAsync(id,c,ct);
        if(plan.IsFailure)return Result<ApprovalDecisionReceipt>.Failure(plan.Error!);
        if(!await Authorized(WarehousePermissions.ScrapApprove,plan.Value!.WarehousePublicId,c,ct))
            return Result<ApprovalDecisionReceipt>.Failure(DeniedError());
        var approval=await approvals.DecideAsync(new ApprovalDecisionCommand(
            "Warehouse","Scrap",id,plan.Value.SnapshotVersion,plan.Value.CreatorActorId,decision,reason,operationKey),c,ct);
        if(approval.IsFailure)return approval;
        if(decision==ApprovalDecisionKind.Approved){
            var marked=await persistence.MarkScrapApprovalAsync(id,approval.Value!.PublicId,c,ct);
            if(marked.IsFailure)return Result<ApprovalDecisionReceipt>.Failure(marked.Error!);
        }
        return approval;
    }

    public async Task<Result<WarehouseMutationReceipt>> PostScrapAsync(
        Guid id,string operationKey,IExecutionContext c,CancellationToken ct)
    {
        var plan=await persistence.GetScrapPostPlanAsync(id,c,ct);
        if(plan.IsFailure)return Result<WarehouseMutationReceipt>.Failure(plan.Error!);
        if(!await Authorized(WarehousePermissions.ScrapPost,plan.Value!.WarehousePublicId,c,ct))return Denied();
        return await transactions.ExecuteAsync(async innerCt=>{
            var p=plan.Value!;
            var movement=await inventory.PostAsync(new InventoryMovementCommand(
                p.ProductPublicId,p.VariantPublicId,p.UomPublicId,p.Quantity,p.ConversionFactorSnapshot,
                p.Source,null,InventorySourceIdentity.Create("Warehouse","Scrap",p.ScrapPublicId,null),
                null,operationKey+".inventory"),c,innerCt);
            if(movement.IsFailure)return Result<WarehouseMutationReceipt>.Failure(movement.Error!);
            return await persistence.CompleteScrapPostAsync(id,movement.Value!.MovementPublicId,c,innerCt);
        },ct);
    }

    public async Task<Result<WarehouseMutationReceipt>> RecordOfflineAsync(
        OfflineOperationCommand command,IExecutionContext c,CancellationToken ct)
    {
        if(!await warehouseAccess.IsGrantedAsync(c.ActorId,c.CompanyId,command.WarehousePublicId,ct))return Denied();
        return await persistence.RecordOfflineAsync(command,c,ct);
    }

    private async Task<bool> Authorized(string permission,Guid warehouse,IExecutionContext c,CancellationToken ct)=>
        await Granted(permission,c,ct)&&
        await warehouseAccess.IsGrantedAsync(c.ActorId,c.CompanyId,warehouse,ct);
    private Task<bool> Granted(string p,IExecutionContext c,CancellationToken ct)=>
        permissions.IsGrantedAsync(c.ActorId,c.CompanyId,p,ct);
    private static Result<WarehouseMutationReceipt> Denied()=>Result<WarehouseMutationReceipt>.Failure(DeniedError());
    private static ApplicationError DeniedError()=>new(ErrorCategory.Authorization,"authorization.permission_denied","Warehouse permission or scope denied.");
    private static Result<WarehouseMutationReceipt> Invalid(string code,string message)=>Result<WarehouseMutationReceipt>.Failure(
        new ApplicationError(ErrorCategory.BusinessRule,code,message));
}
