using Mars.Api.Foundation.Errors;
using Mars.Application.Foundation.Approvals;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Inventory;
using Mars.Application.Warehouse;
using Mars.Domain.Inventory;
using Mars.Domain.Warehouse;

namespace Mars.Api.Warehouse;

public static class WarehouseEndpoints
{
    public static void MapWarehouseEndpoints(this WebApplication app)
    {
        var warehouse=app.MapGroup("/api/v1/warehouse").RequireAuthorization();

        warehouse.MapGet("/receiving",async(IExecutionContext c,WarehouseQueryHandler h,CancellationToken ct)=>
            Map(await h.ListReceivingAsync(c,ct),c)).WithName("ListWarehouseReceiving");

        warehouse.MapGet("/putaway",async(IExecutionContext c,WarehouseQueryHandler h,CancellationToken ct)=>
            Map(await h.ListPutAwayAsync(c,ct),c)).WithName("ListWarehousePutAway");

        warehouse.MapGet("/reservations",async(Guid? warehousePublicId,IExecutionContext c,WarehouseQueryHandler h,CancellationToken ct)=>
            Map(await h.ListReservationsAsync(warehousePublicId,c,ct),c)).WithName("ListWarehouseReservations");

        warehouse.MapGet("/picks",async(IExecutionContext c,WarehouseQueryHandler h,CancellationToken ct)=>
            Map(await h.ListPicksAsync(c,ct),c)).WithName("ListWarehousePicks");

        warehouse.MapGet("/picks/recommendation",async(Guid dispatchPublicId,Guid dispatchLinePublicId,IExecutionContext c,WarehouseQueryHandler h,CancellationToken ct)=>
            Map(await h.RecommendPickAsync(dispatchPublicId,dispatchLinePublicId,c,ct),c)).WithName("RecommendWarehousePick");

        warehouse.MapGet("/transfers",async(IExecutionContext c,WarehouseQueryHandler h,CancellationToken ct)=>
            Map(await h.ListTransfersAsync(c,ct),c)).WithName("ListWarehouseTransfers");

        warehouse.MapGet("/counts",async(IExecutionContext c,WarehouseQueryHandler h,CancellationToken ct)=>
            Map(await h.ListCountsAsync(c,ct),c)).WithName("ListWarehouseCounts");

        warehouse.MapGet("/scrap",async(IExecutionContext c,WarehouseQueryHandler h,CancellationToken ct)=>
            Map(await h.ListScrapAsync(c,ct),c)).WithName("ListWarehouseScrap");

        warehouse.MapGet("/offline-operations",async(IExecutionContext c,WarehouseQueryHandler h,CancellationToken ct)=>
            Map(await h.ListOfflineAsync(c,ct),c)).WithName("ListWarehouseOfflineOperations");

        warehouse.MapGet("/trace",async(
            Guid? productPublicId,Guid? variantPublicId,Guid? warehousePublicId,Guid? locationPublicId,
            InventoryDispositionCode? disposition,Guid? lotPublicId,Guid? serialPublicId,
            IExecutionContext c,WarehouseQueryHandler h,CancellationToken ct)=>
            Map(await h.ListTraceAsync(new InventoryReadFilter(
                productPublicId,variantPublicId,warehousePublicId,locationPublicId,disposition,lotPublicId,serialPublicId),c,ct),c))
            .WithName("ListWarehouseTrace");

        warehouse.MapPost("/dispositions",async(WarehouseDispositionRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.ChangeDispositionAsync(new ChangeDispositionCommand(
                r.ProductPublicId,r.VariantPublicId,r.UomPublicId,r.Quantity,r.ConversionFactorSnapshot,
                Position(r.Source),Position(r.Target),r.Reason,r.SourceDocumentPublicId,r.SourceLinePublicId,Key(http)),c,ct),c))
            .WithName("ChangeWarehouseDisposition");

        warehouse.MapPost("/damage",async(WarehouseDispositionRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.RecordDamageAsync(new ChangeDispositionCommand(
                r.ProductPublicId,r.VariantPublicId,r.UomPublicId,r.Quantity,r.ConversionFactorSnapshot,
                Position(r.Source),Position(r.Target),r.Reason,r.SourceDocumentPublicId,r.SourceLinePublicId,Key(http)),c,ct),c))
            .WithName("RecordWarehouseDamage");

        warehouse.MapPost("/putaway",async(WarehouseInternalMoveRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.ExecuteInternalMoveAsync(new InternalMoveCommand(
                InternalMoveKind.PutAway,r.ProductPublicId,r.VariantPublicId,r.UomPublicId,r.Quantity,r.ConversionFactorSnapshot,
                Position(r.Source),Position(r.Target),r.SourceDocumentPublicId,r.SourceLinePublicId,Key(http)),c,ct),c))
            .WithName("ExecuteWarehousePutAway");

        warehouse.MapPost("/replenishment",async(WarehouseInternalMoveRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.ExecuteInternalMoveAsync(new InternalMoveCommand(
                InternalMoveKind.Replenishment,r.ProductPublicId,r.VariantPublicId,r.UomPublicId,r.Quantity,r.ConversionFactorSnapshot,
                Position(r.Source),Position(r.Target),r.SourceDocumentPublicId,r.SourceLinePublicId,Key(http)),c,ct),c))
            .WithName("ExecuteWarehouseReplenishment");

        warehouse.MapPost("/picks",async(WarehousePickRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/warehouse/picks",await h.RecordPickAsync(new RecordPickCommand(
                r.DispatchPublicId,r.DispatchExpectedVersion,r.DispatchLinePublicId,r.WarehousePublicId,r.Quantity,
                r.LocationPublicId,r.LotPublicId,r.SerialPublicId,r.StrategyOverride,r.OverrideReason,Key(http)),c,ct),c))
            .WithName("RecordWarehousePick");

        warehouse.MapPost("/packages",async(CreateWarehousePackageRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/warehouse/packages",await h.CreatePackageAsync(new CreatePackageCommand(
                r.PackageCode,r.DispatchPublicId,r.WarehousePublicId,
                r.Items.Select(x=>new PackageItemInput(x.DispatchLinePublicId,x.Quantity,x.LotPublicId,x.SerialPublicId)).ToArray(),
                r.TrackingReference,r.CarrierReference,Key(http)),c,ct),c))
            .WithName("CreateWarehousePackage");

        warehouse.MapPost("/staging",async(WarehouseStageLoadRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.StageLoadAsync(new StageLoadCommand(
                r.DispatchPublicId,r.WarehousePublicId,StageLoadKind.Stage,r.PackagePublicIds,Key(http)),c,ct),c))
            .WithName("StageWarehousePackages");

        warehouse.MapPost("/loading",async(WarehouseStageLoadRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.StageLoadAsync(new StageLoadCommand(
                r.DispatchPublicId,r.WarehousePublicId,StageLoadKind.Load,r.PackagePublicIds,Key(http)),c,ct),c))
            .WithName("LoadWarehousePackages");

        warehouse.MapPost("/transfers",async(CreateWarehouseTransferRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/warehouse/transfers",await h.CreateTransferAsync(new CreateTransferCommand(
                r.Number,r.SourceWarehousePublicId,r.TargetWarehousePublicId,
                r.Lines.Select(x=>new TransferLineInput(x.Sequence,x.ProductPublicId,x.VariantPublicId,x.UomPublicId,x.Quantity,
                    x.SourceLocationPublicId,x.TargetLocationPublicId,x.LotPublicId,x.SerialPublicId)).ToArray(),Key(http)),c,ct),c))
            .WithName("CreateWarehouseTransfer");

        warehouse.MapPost("/transfers/{id:guid}/issue",async(Guid id,WarehouseVersionRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.IssueTransferAsync(id,r.Version,Key(http),c,ct),c)).WithName("IssueWarehouseTransfer");

        warehouse.MapPost("/transfers/{id:guid}/receive",async(Guid id,WarehouseTransferReceiveRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.ReceiveTransferAsync(new TransferReceiveCommand(
                id,r.Version,r.Lines.Select(x=>new TransferReceiveLineInput(
                    x.TransferLinePublicId,x.Quantity,x.TargetLocationPublicId,x.Disposition)).ToArray(),Key(http)),c,ct),c))
            .WithName("ReceiveWarehouseTransfer");

        warehouse.MapPost("/transfers/{id:guid}/loss/approval",async(Guid id,WarehouseTransferLossApprovalRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            ParseDecision(r.Decision) is { } d
                ? Map(await h.ApproveTransferLossAsync(id,r.TransferLinePublicId,r.Quantity,r.Reason,d,r.ApprovalReason??r.Reason,Key(http),c,ct),c)
                : Invalid("warehouse.approval.decision","Decision must be APPROVED or REJECTED.",c))
            .WithName("ApproveWarehouseTransferLoss");

        warehouse.MapPost("/transfers/{id:guid}/loss/post",async(Guid id,WarehouseTransferLossPostRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.PostTransferLossAsync(id,r.TransferLinePublicId,r.Quantity,r.Reason,Key(http),c,ct),c))
            .WithName("PostWarehouseTransferLoss");

        warehouse.MapPost("/transfers/{id:guid}/close",async(Guid id,WarehouseVersionRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.CloseTransferAsync(id,r.Version,Key(http),c,ct),c)).WithName("CloseWarehouseTransfer");

        warehouse.MapPost("/transfers/{id:guid}/reverse",async(Guid id,WarehouseVersionRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.ReverseTransferAsync(id,r.Version,Key(http),c,ct),c)).WithName("ReverseWarehouseTransfer");

        warehouse.MapPost("/counts",async(CreateWarehouseCountRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/warehouse/counts",await h.CreateCountAsync(
                new CreateCountCommand(r.Number,r.WarehousePublicId,r.LocationPublicIds,Key(http)),c,ct),c))
            .WithName("CreateWarehouseCount");

        warehouse.MapPost("/counts/{id:guid}/start",async(Guid id,WarehouseVersionRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.StartCountAsync(id,r.Version,Key(http),c,ct),c)).WithName("StartWarehouseCount");

        warehouse.MapPost("/counts/{id:guid}/observations",async(Guid id,WarehouseCountObservationsRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.RecordCountAsync(new RecordCountObservationCommand(
                id,r.Version,r.Observations.Select(x=>new CountObservationInput(x.CountLinePublicId,x.CountedQuantity)).ToArray(),
                r.IsRecount,Key(http)),c,ct),c)).WithName("RecordWarehouseCount");

        warehouse.MapPost("/counts/{id:guid}/review",async(Guid id,WarehouseVersionRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.ReviewCountAsync(id,r.Version,Key(http),c,ct),c)).WithName("ReviewWarehouseCount");

        warehouse.MapPost("/counts/{id:guid}/approval",async(Guid id,WarehouseApprovalRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            ParseDecision(r.Decision) is { } d
                ? Map(await h.ApproveCountAsync(id,d,r.Reason,Key(http),c,ct),c)
                : Invalid("warehouse.approval.decision","Decision must be APPROVED or REJECTED.",c))
            .WithName("ApproveWarehouseCount");

        warehouse.MapPost("/counts/{id:guid}/post",async(Guid id,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.PostCountAsync(id,Key(http),c,ct),c)).WithName("PostWarehouseCount");

        warehouse.MapPost("/counts/{id:guid}/close",async(Guid id,WarehouseVersionRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.CloseCountAsync(id,r.Version,Key(http),c,ct),c)).WithName("CloseWarehouseCount");

        warehouse.MapPost("/counts/{id:guid}/reverse",async(Guid id,WarehouseVersionRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.ReverseCountAsync(id,r.Version,Key(http),c,ct),c)).WithName("ReverseWarehouseCount");

        warehouse.MapPost("/scrap",async(CreateWarehouseScrapRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/warehouse/scrap",await h.RequestScrapAsync(new ScrapRequestCommand(
                r.Number,r.WarehousePublicId,r.ProductPublicId,r.VariantPublicId,r.UomPublicId,r.Quantity,r.ConversionFactorSnapshot,
                Position(r.Source),r.Reason,Key(http)),c,ct),c)).WithName("RequestWarehouseScrap");

        warehouse.MapPost("/scrap/{id:guid}/approval",async(Guid id,WarehouseApprovalRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            ParseDecision(r.Decision) is { } d
                ? Map(await h.ApproveScrapAsync(id,d,r.Reason,Key(http),c,ct),c)
                : Invalid("warehouse.approval.decision","Decision must be APPROVED or REJECTED.",c))
            .WithName("ApproveWarehouseScrap");

        warehouse.MapPost("/scrap/{id:guid}/post",async(Guid id,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.PostScrapAsync(id,Key(http),c,ct),c)).WithName("PostWarehouseScrap");

        warehouse.MapPost("/offline-operations",async(WarehouseOfflineOperationRequest r,HttpRequest http,IExecutionContext c,WarehouseCommandHandler h,CancellationToken ct)=>
            Map(await h.RecordOfflineAsync(new OfflineOperationCommand(
                r.ClientOperationId,r.WarehousePublicId,r.OperationType,r.WorkPublicId,r.ExpectedVersion,
                r.ScanIdentity,r.LocalTimestamp,Key(http)),c,ct),c)).WithName("RecordWarehouseOfflineOperation");
    }

    private static InventoryPosition Position(WarehousePositionRequest r)=>
        new(r.WarehousePublicId,r.LocationPublicId,r.Disposition,r.LotPublicId,r.SerialPublicId);

    private static ApprovalDecisionKind? ParseDecision(string value)=>value.Trim().ToUpperInvariant() switch
    {
        "APPROVED"=>ApprovalDecisionKind.Approved,
        "REJECTED"=>ApprovalDecisionKind.Rejected,
        _=>null
    };

    private static string Key(HttpRequest request)=>request.Headers["Idempotency-Key"].ToString();

    private static IResult Map<T>(Result<T> result,IExecutionContext c)=>
        result.IsFailure?ApplicationErrorHttpMapper.ToResult(result.Error!,c.CorrelationId.Value):Results.Ok(result.Value);

    private static IResult Created<T>(string basePath,Result<T> result,IExecutionContext c)=>
        result.IsFailure?ApplicationErrorHttpMapper.ToResult(result.Error!,c.CorrelationId.Value)
        :Results.Created(basePath+"/"+(result.Value is WarehouseMutationReceipt x?x.PublicId:"created"),result.Value);

    private static IResult Invalid(string code,string message,IExecutionContext c)=>
        ApplicationErrorHttpMapper.ToResult(new ApplicationError(ErrorCategory.Validation,code,message),c.CorrelationId.Value);
}
