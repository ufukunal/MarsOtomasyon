using System.Text.Json;
using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Outbox;
using Mars.Application.Foundation.Results;
using Mars.Application.Warehouse;
using Mars.Domain.Inventory;
using Mars.Domain.Products;
using Mars.Domain.Purchasing;
using Mars.Domain.Sales;
using Mars.Domain.Warehouse;
using Mars.Infrastructure.Persistence.Foundation;
using Mars.Infrastructure.Persistence.Inventory;
using Mars.Infrastructure.Persistence.Products;
using Mars.Infrastructure.Persistence.Purchasing;
using Mars.Infrastructure.Persistence.Sales;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;
using Npgsql;

namespace Mars.Infrastructure.Persistence.Warehouse;

public sealed class EfWarehousePersistence(
    MarsDbContext dbContext,
    IAuditWriter auditWriter,
    IIdempotencyStore idempotencyStore,
    IOutboxWriter outboxWriter)
    : IWarehousePersistence
{
    private sealed record Trade(
        long ProductId,long? VariantId,long UomId,decimal Conversion,
        Guid ProductPublicId,Guid? VariantPublicId,Guid UomPublicId);

    public async Task<IReadOnlyList<ReceivingQueueItem>> ListReceivingAsync(Guid companyId,CancellationToken ct)
    {
        var receipts=await (
            from r in dbContext.Set<GoodsReceiptRecord>().AsNoTracking()
            join o in dbContext.Set<PurchaseOrderRecord>().AsNoTracking() on r.PurchaseOrderId equals o.Id
            join l in dbContext.Set<GoodsReceiptLineRecord>().AsNoTracking() on r.Id equals l.GoodsReceiptId
            where r.CompanyId==companyId&&o.CompanyId==companyId&&l.CompanyId==companyId&&r.State==GoodsReceiptState.Posted
            orderby r.PostedAt descending,l.Sequence
            select new {Receipt=r,Order=o,Line=l}).Take(500).ToArrayAsync(ct);

        var products=await Map<ProductRecord>(companyId,receipts.Select(x=>x.Line.ProductId),x=>x.Id,ct);
        var variants=await Map<ProductVariantRecord>(companyId,receipts.Where(x=>x.Line.VariantId.HasValue).Select(x=>x.Line.VariantId!.Value),x=>x.Id,ct);
        var uoms=await Map<UnitOfMeasureRecord>(companyId,receipts.Select(x=>x.Line.UomId),x=>x.Id,ct);
        var warehouses=await Map<WarehouseRecord>(companyId,receipts.Select(x=>x.Line.WarehouseId),x=>x.Id,ct);
        var locations=await Map<LocationRecord>(companyId,receipts.Where(x=>x.Line.LocationId.HasValue).Select(x=>x.Line.LocationId!.Value),x=>x.Id,ct);
        var lots=await Map<InventoryLotRecord>(companyId,receipts.Where(x=>x.Line.LotId.HasValue).Select(x=>x.Line.LotId!.Value),x=>x.Id,ct);
        var serials=await Map<InventorySerialRecord>(companyId,receipts.Where(x=>x.Line.SerialId.HasValue).Select(x=>x.Line.SerialId!.Value),x=>x.Id,ct);

        var dispositionRows=await dbContext.Set<WarehouseDispositionRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&x.SourceDisposition==InventoryDispositionCode.Quarantine&&x.SourceDocumentPublicId!=null)
            .GroupBy(x=>new{x.SourceDocumentPublicId,x.SourceLinePublicId})
            .Select(g=>new{g.Key.SourceDocumentPublicId,g.Key.SourceLinePublicId,Qty=g.Sum(x=>x.Quantity)})
            .ToArrayAsync(ct);
        var disposed=dispositionRows.ToDictionary(
            x=>(x.SourceDocumentPublicId!.Value,x.SourceLinePublicId),
            x=>x.Qty);

        return receipts.Select(x=>{
            var line=x.Line;
            var used=disposed.GetValueOrDefault((x.Receipt.PublicId,(Guid?)line.PublicId));
            return new ReceivingQueueItem(
                x.Receipt.PublicId,x.Receipt.Number,x.Order.PublicId,line.PublicId,
                products[line.ProductId].PublicId,
                line.VariantId.HasValue?variants[line.VariantId.Value].PublicId:null,
                uoms[line.UomId].PublicId,warehouses[line.WarehouseId].PublicId,
                line.LocationId.HasValue?locations[line.LocationId.Value].PublicId:null,
                line.LotId.HasValue?lots[line.LotId.Value].PublicId:null,
                line.SerialId.HasValue?serials[line.SerialId.Value].PublicId:null,
                line.Quantity,Math.Max(0m,line.Quantity-used),x.Receipt.PostedAt??x.Receipt.CreatedAt);
        }).ToArray();
    }

    public Task<IReadOnlyList<WarehouseWorkListItem>> ListPutAwayAsync(Guid companyId,CancellationToken ct)=>
        ListOperations(companyId,ct);
    public async Task<IReadOnlyList<WarehouseWorkListItem>> ListPicksAsync(Guid companyId,CancellationToken ct)
    {
        var rows=await dbContext.Set<PickWorkRecord>().AsNoTracking().Where(x=>x.CompanyId==companyId)
            .OrderByDescending(x=>x.CreatedAt).Take(250).ToArrayAsync(ct);
        var wh=await Map<WarehouseRecord>(companyId,rows.Select(x=>x.WarehouseId),x=>x.Id,ct);
        return rows.Select(x=>new WarehouseWorkListItem(x.PublicId,x.DispatchPublicId.ToString("D"),"PICK",
            x.State.ToString().ToUpperInvariant(),wh[x.WarehouseId].PublicId,x.Version,x.CreatedAt)).ToArray();
    }
    public async Task<IReadOnlyList<WarehouseWorkListItem>> ListTransfersAsync(Guid companyId,CancellationToken ct)
    {
        var rows=await dbContext.Set<WarehouseTransferRecord>().AsNoTracking().Where(x=>x.CompanyId==companyId)
            .OrderByDescending(x=>x.CreatedAt).Take(250).ToArrayAsync(ct);
        var wh=await Map<WarehouseRecord>(companyId,rows.Select(x=>x.SourceWarehouseId),x=>x.Id,ct);
        return rows.Select(x=>new WarehouseWorkListItem(x.PublicId,x.Number,"TRANSFER",
            x.State.ToString().ToUpperInvariant(),wh[x.SourceWarehouseId].PublicId,x.Version,x.CreatedAt)).ToArray();
    }
    public async Task<IReadOnlyList<WarehouseWorkListItem>> ListCountsAsync(Guid companyId,CancellationToken ct)
    {
        var rows=await dbContext.Set<StockCountSessionRecord>().AsNoTracking().Where(x=>x.CompanyId==companyId)
            .OrderByDescending(x=>x.CreatedAt).Take(250).ToArrayAsync(ct);
        var wh=await Map<WarehouseRecord>(companyId,rows.Select(x=>x.WarehouseId),x=>x.Id,ct);
        return rows.Select(x=>new WarehouseWorkListItem(x.PublicId,x.Number,"COUNT",
            x.State.ToString().ToUpperInvariant(),wh[x.WarehouseId].PublicId,x.Version,x.CreatedAt)).ToArray();
    }
    public async Task<IReadOnlyList<WarehouseWorkListItem>> ListScrapAsync(Guid companyId,CancellationToken ct)
    {
        var rows=await dbContext.Set<WarehouseScrapRecord>().AsNoTracking().Where(x=>x.CompanyId==companyId)
            .OrderByDescending(x=>x.CreatedAt).Take(250).ToArrayAsync(ct);
        var wh=await Map<WarehouseRecord>(companyId,rows.Select(x=>x.WarehouseId),x=>x.Id,ct);
        return rows.Select(x=>new WarehouseWorkListItem(x.PublicId,x.Number,"SCRAP",
            x.State.ToString().ToUpperInvariant(),wh[x.WarehouseId].PublicId,x.Version,x.CreatedAt)).ToArray();
    }
    public async Task<IReadOnlyList<OfflineOperationView>> ListOfflineAsync(Guid companyId,CancellationToken ct)
    {
        var rows=await dbContext.Set<WarehouseOfflineOperationRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId).OrderByDescending(x=>x.CreatedAt).Take(250).ToArrayAsync(ct);
        var wh=await Map<WarehouseRecord>(companyId,rows.Select(x=>x.WarehouseId),x=>x.Id,ct);
        return rows.Select(x=>new OfflineOperationView(
            x.PublicId,x.ClientOperationId,wh[x.WarehouseId].PublicId,x.OperationType,
            x.State.ToString().ToUpperInvariant(),x.ConflictCode,x.ServerResultPublicId,x.LocalTimestamp,x.CreatedAt)).ToArray();
    }

    public async Task<Result<WarehouseMutationReceipt>> CompleteDispositionAsync(
        ChangeDispositionCommand command,Guid movementPublicId,IExecutionContext context,CancellationToken ct)=>
        await Mutate("warehouse.disposition",command.OperationKey,"WarehouseDispositionChanged","WarehouseDisposition",context,async inner=>{
            if(command.Quantity<=0m||command.ConversionFactorSnapshot<=0m||string.IsNullOrWhiteSpace(command.Reason))
                return Invalid("warehouse.disposition.invalid","Positive quantity/conversion and reason are required.");
            var trade=await ResolveTrade(context.CompanyId,command.ProductPublicId,command.VariantPublicId,command.UomPublicId,inner);
            if(trade is null)return Invalid("warehouse.disposition.trade","Product/Variant/UOM identity is invalid.");
            var src=await ResolvePosition(context.CompanyId,command.Source,trade,inner);
            if(src is null)return Invalid("warehouse.disposition.source","Source physical position is invalid.");
            var publicId=Guid.NewGuid();
            dbContext.Add(new WarehouseDispositionRecord{
                PublicId=publicId,CompanyId=context.CompanyId,WarehouseId=src.Value.WarehouseId,LocationId=src.Value.LocationId,
                ProductId=trade.ProductId,VariantId=trade.VariantId,UomId=trade.UomId,LotId=src.Value.LotId,SerialId=src.Value.SerialId,
                SourceDisposition=command.Source.Disposition,TargetDisposition=command.Target.Disposition,
                Quantity=command.Quantity,Reason=command.Reason.Trim(),InventoryMovementPublicId=movementPublicId,
                SourceDocumentPublicId=command.SourceDocumentPublicId,SourceLinePublicId=command.SourceLinePublicId,
                CreatorActorId=context.ActorId,CreatedAt=DateTimeOffset.UtcNow
            });
            return Success(publicId,"COMPLETED",1,context);
        },ct);

    public async Task<Result<WarehouseMutationReceipt>> CompleteInternalMoveAsync(
        InternalMoveCommand command,Guid movementPublicId,IExecutionContext context,CancellationToken ct)=>
        await Mutate("warehouse.internal_move",command.OperationKey,"WarehouseInternalMoveCompleted","WarehouseOperation",context,async inner=>{
            var trade=await ResolveTrade(context.CompanyId,command.ProductPublicId,command.VariantPublicId,command.UomPublicId,inner);
            if(trade is null)return Invalid("warehouse.move.trade","Product/Variant/UOM identity is invalid.");
            var src=await ResolvePosition(context.CompanyId,command.Source,trade,inner);
            var dst=await ResolvePosition(context.CompanyId,command.Target,trade,inner);
            if(src is null||dst is null||src.Value.WarehouseId!=dst.Value.WarehouseId)
                return Invalid("warehouse.move.position","Internal movement positions are invalid.");
            var targetLocation=dst.Value.LocationId.HasValue
                ? await dbContext.Set<LocationRecord>().AsNoTracking().SingleAsync(x=>x.Id==dst.Value.LocationId&&x.CompanyId==context.CompanyId,inner)
                : null;
            if(targetLocation is null||targetLocation.State!=InventoryMasterState.Active||!targetLocation.StockBearing)
                return Invalid("warehouse.move.target","Target Location must be ACTIVE and stock-bearing.");
            var publicId=Guid.NewGuid(); var now=DateTimeOffset.UtcNow;
            dbContext.Add(new WarehouseOperationRecord{
                PublicId=publicId,CompanyId=context.CompanyId,Kind=command.Kind,WarehouseId=src.Value.WarehouseId,
                SourceLocationId=src.Value.LocationId,TargetLocationId=dst.Value.LocationId,ProductId=trade.ProductId,VariantId=trade.VariantId,
                UomId=trade.UomId,LotId=src.Value.LotId,SerialId=src.Value.SerialId,Disposition=command.Source.Disposition,
                Quantity=command.Quantity,InventoryMovementPublicId=movementPublicId,
                SourceDocumentPublicId=command.SourceDocumentPublicId,SourceLinePublicId=command.SourceLinePublicId,
                State=WarehouseWorkState.Completed,Version=1,CreatorActorId=context.ActorId,CreatedAt=now,CompletedAt=now
            });
            return Success(publicId,"COMPLETED",1,context);
        },ct);

    public async Task<Result<PickPlan>> PreparePickAsync(RecordPickCommand command,IExecutionContext context,CancellationToken ct)
    {
        if(command.Quantity<=0m||command.DispatchPublicId==Guid.Empty||command.DispatchLinePublicId==Guid.Empty)
            return Fail<PickPlan>("warehouse.pick.invalid","Dispatch line and positive quantity are required.");
        var dispatch=await dbContext.Set<DispatchRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==command.DispatchPublicId,ct);
        if(dispatch is null||dispatch.State is not (DispatchState.Draft or DispatchState.Ready))
            return Fail<PickPlan>("warehouse.pick.dispatch_state","Dispatch must be DRAFT or READY.");
        var warehouse=await dbContext.Set<WarehouseRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.Id==dispatch.WarehouseId&&x.CompanyId==context.CompanyId&&x.PublicId==command.WarehousePublicId,ct);
        if(warehouse is null||warehouse.State!=InventoryMasterState.Active)
            return Fail<PickPlan>("warehouse.pick.warehouse","Dispatch Warehouse must be ACTIVE.");
        var line=await dbContext.Set<DispatchLineRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.DispatchId==dispatch.Id&&x.PublicId==command.DispatchLinePublicId,ct);
        if(line is null)return Fail<PickPlan>("warehouse.pick.line","Dispatch line was not found.");
        var picked=await dbContext.Set<PickWorkRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&x.DispatchPublicId==dispatch.PublicId&&x.DispatchLinePublicId==line.PublicId&&
                x.State!=PickWorkState.Cancelled).SumAsync(x=>(decimal?)x.Quantity,ct)??0m;
        if(picked+command.Quantity>line.Quantity)
            return Fail<PickPlan>("warehouse.pick.over_pick","Cumulative pick cannot exceed Dispatch line quantity.");

        var product=await dbContext.Set<ProductRecord>().AsNoTracking()
            .SingleAsync(x=>x.Id==line.ProductId&&x.CompanyId==context.CompanyId,ct);
        if(!product.Stockable||product.State!=ProductState.Active)
            return Fail<PickPlan>("warehouse.pick.product","Pick requires ACTIVE STOCKABLE Product.");
        var location=await dbContext.Set<LocationRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.WarehouseId==warehouse.Id&&x.PublicId==command.LocationPublicId,ct);
        if(location is null||location.State!=InventoryMasterState.Active||!location.StockBearing)
            return Fail<PickPlan>("warehouse.pick.location","Pick Location must be ACTIVE and stock-bearing.");
        var lot=await ResolveLot(context.CompanyId,line.ProductId,line.VariantId,command.LotPublicId,ct);
        if(command.LotPublicId.HasValue&&lot is null)return Fail<PickPlan>("warehouse.pick.lot","Lot identity mismatch.");
        if(lot?.ExpiryDate is DateOnly expiry&&expiry<DateOnly.FromDateTime(DateTime.UtcNow))
            return Fail<PickPlan>("warehouse.pick.expired","Expired Lot cannot be normally picked.");
        var serial=await ResolveSerial(context.CompanyId,line.ProductId,line.VariantId,command.SerialPublicId,ct);
        if(command.SerialPublicId.HasValue&&serial is null)return Fail<PickPlan>("warehouse.pick.serial","Serial identity mismatch.");
        var position=new InventoryPosition(warehouse.PublicId,location.PublicId,InventoryDispositionCode.Available,lot?.PublicId,serial?.PublicId);
        var available=await PositionBalance(context.CompanyId,line.ProductId,line.VariantId,location.Id,
            InventoryDispositionCode.Available,lot?.Id,serial?.Id,ct);
        var baseQty=command.Quantity*line.ConversionFactorSnapshot;
        if(available<baseQty)return Fail<PickPlan>("warehouse.pick.insufficient","AVAILABLE physical quantity is insufficient.");

        if(line.ReservationPublicId.HasValue){
            var reservation=await dbContext.Set<InventoryReservationRecord>().AsNoTracking()
                .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==line.ReservationPublicId.Value,ct);
            if(reservation is null)return Fail<PickPlan>("warehouse.pick.reservation","Linked Reservation was not found.");
            var remaining=await ReservationBalance(reservation.Id,context.CompanyId,ct);
            if((picked+command.Quantity)*line.ConversionFactorSnapshot>remaining)
                return Fail<PickPlan>("warehouse.pick.reservation_limit","Pick cannot exceed linked Reservation remaining quantity.");
        }

        var strategy=lot?.ExpiryDate.HasValue==true?PickStrategy.Fefo:PickStrategy.Fifo;
        if(!command.StrategyOverride){
            var candidates=await RecommendPickAsync(context.CompanyId,command.DispatchPublicId,command.DispatchLinePublicId,ct);
            var first=candidates.FirstOrDefault();
            if(first is not null&&(first.LocationPublicId!=location.PublicId||first.LotPublicId!=lot?.PublicId||first.SerialPublicId!=serial?.PublicId))
                return Fail<PickPlan>("warehouse.pick.strategy","Selected source is not the current FIFO/FEFO recommendation; use controlled override.");
        }

        var uom=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking().SingleAsync(x=>x.Id==line.UomId&&x.CompanyId==context.CompanyId,ct);
        var variant=line.VariantId.HasValue
            ? await dbContext.Set<ProductVariantRecord>().AsNoTracking().SingleAsync(x=>x.Id==line.VariantId&&x.CompanyId==context.CompanyId,ct)
            : null;
        return Result<PickPlan>.Success(new PickPlan(
            Guid.NewGuid(),command.OperationKey,dispatch.PublicId,line.PublicId,product.PublicId,variant?.PublicId,uom.PublicId,warehouse.PublicId,
            command.Quantity,line.ConversionFactorSnapshot,location.PublicId,lot?.PublicId,serial?.PublicId,line.ReservationPublicId,
            command.StrategyOverride,Normalize(command.OverrideReason)));
    }

    public async Task<Result<WarehouseMutationReceipt>> CompletePickAsync(PickPlan p,IExecutionContext context,CancellationToken ct)=>
        await Mutate("warehouse.pick",p.OperationKey,"WarehousePickCompleted","PickWork",context,async inner=>{
            var dispatch=await dbContext.Set<DispatchRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==p.DispatchPublicId,inner);
            var line=await dbContext.Set<DispatchLineRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==p.DispatchLinePublicId,inner);
            var warehouse=await dbContext.Set<WarehouseRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==p.WarehousePublicId,inner);
            var location=await dbContext.Set<LocationRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==p.LocationPublicId,inner);
            var product=await dbContext.Set<ProductRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==p.ProductPublicId,inner);
            var variant=p.VariantPublicId.HasValue?await dbContext.Set<ProductVariantRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==p.VariantPublicId,inner):null;
            var uom=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==p.UomPublicId,inner);
            var lot=p.LotPublicId.HasValue?await dbContext.Set<InventoryLotRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==p.LotPublicId,inner):null;
            var serial=p.SerialPublicId.HasValue?await dbContext.Set<InventorySerialRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==p.SerialPublicId,inner):null;
            dbContext.Add(new PickWorkRecord{
                PublicId=p.PickWorkPublicId,CompanyId=context.CompanyId,DispatchPublicId=dispatch.PublicId,DispatchLinePublicId=line.PublicId,
                WarehouseId=warehouse.Id,LocationId=location.Id,ProductId=product.Id,VariantId=variant?.Id,UomId=uom.Id,
                ConversionFactorSnapshot=p.ConversionFactorSnapshot,LotId=lot?.Id,SerialId=serial?.Id,ReservationPublicId=p.ReservationPublicId,
                Quantity=p.Quantity,Strategy=lot?.ExpiryDate.HasValue==true?PickStrategy.Fefo:PickStrategy.Fifo,
                StrategyOverride=p.StrategyOverride,OverrideReason=p.OverrideReason,State=PickWorkState.Picked,Version=1,
                CreatorActorId=context.ActorId,CreatedAt=DateTimeOffset.UtcNow
            });
            return Success(p.PickWorkPublicId,"PICKED",1,context);
        },ct);

    public async Task<IReadOnlyList<PickCandidateView>> RecommendPickAsync(
        Guid companyId,Guid dispatchPublicId,Guid dispatchLinePublicId,CancellationToken ct)
    {
        var dispatch=await dbContext.Set<DispatchRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==dispatchPublicId,ct);
        if(dispatch is null)return [];
        var line=await dbContext.Set<DispatchLineRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.DispatchId==dispatch.Id&&x.PublicId==dispatchLinePublicId,ct);
        if(line is null)return [];
        var wh=await dbContext.Set<WarehouseRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==companyId&&x.Id==dispatch.WarehouseId,ct);
        var candidates=await (
            from m in dbContext.Set<InventoryMovementRecord>().AsNoTracking()
            where m.CompanyId==companyId&&m.ProductId==line.ProductId&&m.VariantId==line.VariantId&&
                  m.TargetWarehouseId==wh.Id&&m.TargetLocationId!=null&&m.TargetDispositionId!=null
            join d in dbContext.Set<InventoryDispositionRecord>().AsNoTracking() on m.TargetDispositionId equals d.Id
            where d.Code==InventoryDispositionCode.Available
            select new {m.TargetLocationId,m.LotId,m.SerialId,m.PostedAt}).Distinct().Take(250).ToArrayAsync(ct);
        var locations=await Map<LocationRecord>(companyId,candidates.Select(x=>x.TargetLocationId!.Value),x=>x.Id,ct);
        var lots=await Map<InventoryLotRecord>(companyId,candidates.Where(x=>x.LotId.HasValue).Select(x=>x.LotId!.Value),x=>x.Id,ct);
        var serials=await Map<InventorySerialRecord>(companyId,candidates.Where(x=>x.SerialId.HasValue).Select(x=>x.SerialId!.Value),x=>x.Id,ct);
        var product=await dbContext.Set<ProductRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==companyId&&x.Id==line.ProductId,ct);
        var variant=line.VariantId.HasValue?await dbContext.Set<ProductVariantRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==companyId&&x.Id==line.VariantId,ct):null;
        var uom=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==companyId&&x.Id==line.UomId,ct);
        var now=DateOnly.FromDateTime(DateTime.UtcNow);
        var list=new List<(PickCandidateView View,DateOnly? Expiry)>();
        foreach(var x in candidates){
            var loc=locations[x.TargetLocationId!.Value];
            if(loc.State!=InventoryMasterState.Active||!loc.StockBearing)continue;
            lots.TryGetValue(x.LotId??-1,out var lot);
            if(lot?.ExpiryDate is DateOnly exp&&exp<now)continue;
            var qty=await PositionBalance(companyId,line.ProductId,line.VariantId,loc.Id,InventoryDispositionCode.Available,x.LotId,x.SerialId,ct);
            if(qty<=0m)continue;
            list.Add((new PickCandidateView(
                product.PublicId,variant?.PublicId,uom.PublicId,wh.PublicId,loc.PublicId,
                lot?.PublicId,x.SerialId.HasValue?serials[x.SerialId.Value].PublicId:null,
                qty/line.ConversionFactorSnapshot,lot?.ExpiryDate,x.PostedAt,
                lot?.ExpiryDate.HasValue==true?PickStrategy.Fefo:PickStrategy.Fifo,0),lot?.ExpiryDate));
        }
        var expiryTracked=list.Any(x=>x.Expiry.HasValue);
        var ordered=(expiryTracked
            ? list.OrderBy(x=>x.Expiry??DateOnly.MaxValue).ThenBy(x=>x.View.FirstAvailableAt).ThenBy(x=>x.View.LocationPublicId)
            : list.OrderBy(x=>x.View.FirstAvailableAt).ThenBy(x=>x.View.LocationPublicId)).ToArray();
        return ordered.Select((x,i)=>x.View with {Rank=i+1,Strategy=expiryTracked?PickStrategy.Fefo:PickStrategy.Fifo}).ToArray();
    }

    public async Task<Result<WarehouseMutationReceipt>> CreatePackageAsync(
        CreatePackageCommand command,IExecutionContext context,CancellationToken ct)=>
        await Mutate("warehouse.package",command.OperationKey,"WarehousePackageCreated","Package",context,async inner=>{
            if(string.IsNullOrWhiteSpace(command.PackageCode)||command.Items.Count==0)
                return Invalid("warehouse.package.invalid","Package code and items are required.");
            var dispatch=await dbContext.Set<DispatchRecord>().AsNoTracking()
                .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==command.DispatchPublicId,inner);
            if(dispatch is null||dispatch.State is not (DispatchState.Draft or DispatchState.Ready))
                return Invalid("warehouse.package.dispatch","Package requires an unposted Dispatch.");
            var warehouse=await dbContext.Set<WarehouseRecord>().AsNoTracking()
                .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==command.WarehousePublicId&&x.Id==dispatch.WarehouseId,inner);
            if(warehouse is null)return Invalid("warehouse.package.warehouse","Package Warehouse does not match Dispatch.");

            foreach(var input in command.Items){
                if(input.Quantity<=0m)return Invalid("warehouse.package.quantity","Package quantity must be positive.");
                var line=await dbContext.Set<DispatchLineRecord>().AsNoTracking()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.DispatchId==dispatch.Id&&x.PublicId==input.DispatchLinePublicId,inner);
                if(line is null)return Invalid("warehouse.package.line","Package line must belong to Dispatch.");
                var picked=await dbContext.Set<PickWorkRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==context.CompanyId&&x.DispatchPublicId==dispatch.PublicId&&x.DispatchLinePublicId==line.PublicId&&x.State!=PickWorkState.Cancelled)
                    .SumAsync(x=>(decimal?)x.Quantity,inner)??0m;
                var packed=await (
                    from item in dbContext.Set<PackageItemRecord>().AsNoTracking()
                    join pkg in dbContext.Set<PackageRecord>().AsNoTracking() on item.PackageId equals pkg.Id
                    where item.CompanyId==context.CompanyId&&pkg.CompanyId==context.CompanyId&&pkg.DispatchPublicId==dispatch.PublicId&&
                          item.DispatchLinePublicId==line.PublicId&&pkg.State!=PackageState.Cancelled
                    select (decimal?)item.Quantity).SumAsync(inner)??0m;
                if(packed+input.Quantity>picked)return Invalid("warehouse.package.over_pack","Cumulative packed quantity cannot exceed picked quantity.");
            }

            var pkg=new PackageRecord{
                PublicId=Guid.NewGuid(),CompanyId=context.CompanyId,PackageCode=command.PackageCode.Trim(),
                DispatchPublicId=dispatch.PublicId,WarehouseId=warehouse.Id,State=PackageState.Packed,
                TrackingReference=Normalize(command.TrackingReference),CarrierReference=Normalize(command.CarrierReference),
                Version=1,CreatorActorId=context.ActorId,CreatedAt=DateTimeOffset.UtcNow
            };
            dbContext.Add(pkg); await dbContext.SaveChangesAsync(inner);
            foreach(var input in command.Items){
                long? lotId=null,serialId=null;
                if(input.LotPublicId.HasValue)lotId=(await dbContext.Set<InventoryLotRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==input.LotPublicId,inner)).Id;
                if(input.SerialPublicId.HasValue)serialId=(await dbContext.Set<InventorySerialRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==input.SerialPublicId,inner)).Id;
                dbContext.Add(new PackageItemRecord{
                    PublicId=Guid.NewGuid(),PackageId=pkg.Id,CompanyId=context.CompanyId,
                    DispatchLinePublicId=input.DispatchLinePublicId,Quantity=input.Quantity,LotId=lotId,SerialId=serialId
                });
            }
            return Success(pkg.PublicId,"PACKED",pkg.Version,context);
        },ct);

    public async Task<Result<WarehouseMutationReceipt>> RecordStageLoadAsync(
        StageLoadCommand command,IExecutionContext context,CancellationToken ct)=>
        await Mutate("warehouse.stage_load",command.OperationKey,"WarehouseStageLoadCompleted","StageLoadWork",context,async inner=>{
            if(command.PackagePublicIds.Count==0)return Invalid("warehouse.stage_load.packages","At least one Package is required.");
            var warehouse=await dbContext.Set<WarehouseRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==command.WarehousePublicId,inner);
            if(warehouse is null)return Invalid("warehouse.stage_load.warehouse","Warehouse was not found.");
            var packages=await dbContext.Set<PackageRecord>().Where(x=>x.CompanyId==context.CompanyId&&x.DispatchPublicId==command.DispatchPublicId&&
                command.PackagePublicIds.Contains(x.PublicId)).ToArrayAsync(inner);
            if(packages.Length!=command.PackagePublicIds.Distinct().Count()||packages.Any(x=>x.WarehouseId!=warehouse.Id||x.State==PackageState.Cancelled))
                return Invalid("warehouse.stage_load.package","All Packages must belong to the same Dispatch/Warehouse.");
            if(command.Kind==StageLoadKind.Load&&packages.Any(x=>x.State is not (PackageState.Packed or PackageState.Staged)))
                return Invalid("warehouse.load.package_state","Only PACKED/STAGED Packages can be loaded.");

            var work=new StageLoadWorkRecord{
                PublicId=Guid.NewGuid(),CompanyId=context.CompanyId,DispatchPublicId=command.DispatchPublicId,WarehouseId=warehouse.Id,
                Kind=command.Kind,State=WarehouseWorkState.Completed,Version=1,CreatorActorId=context.ActorId,CreatedAt=DateTimeOffset.UtcNow
            };
            dbContext.Add(work); await dbContext.SaveChangesAsync(inner);
            foreach(var pkg in packages){
                pkg.State=command.Kind==StageLoadKind.Stage?PackageState.Staged:PackageState.Loaded;
                pkg.Version++;
                dbContext.Add(new StageLoadPackageRecord{StageLoadWorkId=work.Id,CompanyId=context.CompanyId,PackageId=pkg.Id});
            }
            return Success(work.PublicId,"COMPLETED",1,context);
        },ct);

    public async Task<Result<WarehouseMutationReceipt>> CreateTransferAsync(
        CreateTransferCommand command,IExecutionContext context,CancellationToken ct)=>
        await Mutate("warehouse.transfer.create",command.OperationKey,"WarehouseTransferCreated","WarehouseTransfer",context,async inner=>{
            if(string.IsNullOrWhiteSpace(command.Number)||command.Lines.Count==0||
               command.Lines.Select(x=>x.Sequence).Distinct().Count()!=command.Lines.Count)
                return Invalid("warehouse.transfer.invalid","Transfer number and unique lines are required.");
            var source=await ActiveWarehouse(context.CompanyId,command.SourceWarehousePublicId,inner);
            var target=await ActiveWarehouse(context.CompanyId,command.TargetWarehousePublicId,inner);
            if(source is null||target is null||source.Id==target.Id)return Invalid("warehouse.transfer.warehouse","Distinct ACTIVE source/target Warehouses are required.");
            var transfer=new WarehouseTransferRecord{
                PublicId=Guid.NewGuid(),CompanyId=context.CompanyId,Number=command.Number.Trim(),
                SourceWarehouseId=source.Id,TargetWarehouseId=target.Id,State=TransferState.Draft,Version=1,
                CreatorActorId=context.ActorId,CreatedAt=DateTimeOffset.UtcNow
            };
            dbContext.Add(transfer);await dbContext.SaveChangesAsync(inner);
            foreach(var input in command.Lines.OrderBy(x=>x.Sequence)){
                if(input.Quantity<=0m)return Invalid("warehouse.transfer.quantity","Transfer quantity must be positive.");
                var trade=await ResolveTrade(context.CompanyId,input.ProductPublicId,input.VariantPublicId,input.UomPublicId,inner);
                if(trade is null)return Invalid("warehouse.transfer.trade","Product/Variant/UOM identity is invalid.");
                long? srcLoc=null;
                if(input.SourceLocationPublicId.HasValue){
                    var l=await ActiveLocation(context.CompanyId,source.Id,input.SourceLocationPublicId.Value,inner);
                    if(l is null)return Invalid("warehouse.transfer.source_location","Source Location is invalid.");
                    srcLoc=l.Id;
                }
                var targetLoc=await ActiveLocation(context.CompanyId,target.Id,input.TargetLocationPublicId,inner);
                if(targetLoc is null)return Invalid("warehouse.transfer.target_location","Target Location is invalid.");
                var lot=await ResolveLot(context.CompanyId,trade.ProductId,trade.VariantId,input.LotPublicId,inner);
                if(input.LotPublicId.HasValue&&lot is null)return Invalid("warehouse.transfer.lot","Lot mismatch.");
                var serial=await ResolveSerial(context.CompanyId,trade.ProductId,trade.VariantId,input.SerialPublicId,inner);
                if(input.SerialPublicId.HasValue&&serial is null)return Invalid("warehouse.transfer.serial","Serial mismatch.");
                dbContext.Add(new WarehouseTransferLineRecord{
                    PublicId=Guid.NewGuid(),TransferId=transfer.Id,CompanyId=context.CompanyId,Sequence=input.Sequence,
                    ProductId=trade.ProductId,VariantId=trade.VariantId,UomId=trade.UomId,ConversionFactorSnapshot=trade.Conversion,
                    RequestedQuantity=input.Quantity,IssuedQuantity=0,ReceivedQuantity=0,DamagedReceivedQuantity=0,ResolvedLossQuantity=0,
                    SourceLocationId=srcLoc,TargetLocationId=targetLoc.Id,LotId=lot?.Id,SerialId=serial?.Id
                });
            }
            return Success(transfer.PublicId,"DRAFT",transfer.Version,context);
        },ct);

    public async Task<Guid?> GetTransferWarehouseAsync(Guid companyId,Guid transferPublicId,bool target,CancellationToken ct)
    {
        var row=await dbContext.Set<WarehouseTransferRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==transferPublicId,ct);
        if(row is null)return null;
        var id=target?row.TargetWarehouseId:row.SourceWarehouseId;
        return await dbContext.Set<WarehouseRecord>().AsNoTracking().Where(x=>x.CompanyId==companyId&&x.Id==id).Select(x=>(Guid?)x.PublicId).SingleAsync(ct);
    }

    public async Task<Result<TransferIssuePlan>> PrepareTransferIssueAsync(
        Guid id,long expectedVersion,IExecutionContext context,CancellationToken ct)
    {
        var transfer=await LockTransfer(context.CompanyId,id,ct);
        if(transfer is null)return Fail<TransferIssuePlan>("warehouse.transfer.not_found","Transfer was not found.");
        if(transfer.Version!=expectedVersion)return Conflict<TransferIssuePlan>("warehouse.transfer.stale","Transfer version is stale.");
        if(transfer.State!=TransferState.Draft)return Fail<TransferIssuePlan>("warehouse.transfer.issue_state","Only DRAFT Transfer can be issued.");
        var source=await dbContext.Set<WarehouseRecord>().AsNoTracking().SingleAsync(x=>x.Id==transfer.SourceWarehouseId&&x.CompanyId==context.CompanyId,ct);
        var target=await dbContext.Set<WarehouseRecord>().AsNoTracking().SingleAsync(x=>x.Id==transfer.TargetWarehouseId&&x.CompanyId==context.CompanyId,ct);
        var lines=await dbContext.Set<WarehouseTransferLineRecord>().AsNoTracking().Where(x=>x.TransferId==transfer.Id&&x.CompanyId==context.CompanyId).OrderBy(x=>x.Sequence).ToArrayAsync(ct);
        var result=new List<TransferIssueLinePlan>();
        foreach(var l in lines){
            var ids=await PublicTrade(context.CompanyId,l,ct);
            var sourceLoc=l.SourceLocationId.HasValue?await dbContext.Set<LocationRecord>().AsNoTracking().SingleAsync(x=>x.Id==l.SourceLocationId&&x.CompanyId==context.CompanyId,ct):null;
            var lot=l.LotId.HasValue?await dbContext.Set<InventoryLotRecord>().AsNoTracking().SingleAsync(x=>x.Id==l.LotId&&x.CompanyId==context.CompanyId,ct):null;
            var serial=l.SerialId.HasValue?await dbContext.Set<InventorySerialRecord>().AsNoTracking().SingleAsync(x=>x.Id==l.SerialId&&x.CompanyId==context.CompanyId,ct):null;
            result.Add(new(l.PublicId,ids.Product,ids.Variant,ids.Uom,l.ConversionFactorSnapshot,l.RequestedQuantity,
                new InventoryPosition(source.PublicId,sourceLoc?.PublicId,InventoryDispositionCode.Available,lot?.PublicId,serial?.PublicId),
                new InventoryPosition(target.PublicId,null,InventoryDispositionCode.Transit,lot?.PublicId,serial?.PublicId)));
        }
        return Result<TransferIssuePlan>.Success(new(transfer.PublicId,source.PublicId,target.PublicId,result));
    }

    public async Task<Result<WarehouseMutationReceipt>> CompleteTransferIssueAsync(
        Guid id,IReadOnlyList<WarehouseInventoryEffect> effects,string operationKey,IExecutionContext context,CancellationToken ct)=>
        await Mutate("warehouse.transfer.issue",operationKey,"WarehouseTransferIssued","WarehouseTransfer",context,async inner=>{
            var transfer=await LockTransfer(context.CompanyId,id,inner);
            if(transfer is null||transfer.State!=TransferState.Draft)return Invalid("warehouse.transfer.issue_state","Transfer is not DRAFT.");
            var lines=await dbContext.Set<WarehouseTransferLineRecord>().Where(x=>x.CompanyId==context.CompanyId&&x.TransferId==transfer.Id).ToArrayAsync(inner);
            if(effects.Count!=lines.Length)return Invalid("warehouse.transfer.issue_effects","Every Transfer line requires one issue effect.");
            foreach(var line in lines){
                var effect=effects.SingleOrDefault(x=>x.WorkLinePublicId==line.PublicId);
                if(effect is null)return Invalid("warehouse.transfer.issue_effect","Transfer issue effect is missing.");
                line.IssuedQuantity=line.RequestedQuantity;
                dbContext.Add(new WarehouseTransferEffectRecord{
                    PublicId=Guid.NewGuid(),TransferLineId=line.Id,CompanyId=context.CompanyId,
                    InventoryMovementPublicId=effect.InventoryMovementPublicId,EffectKind="ISSUE",CreatedAt=DateTimeOffset.UtcNow
                });
            }
            transfer.State=TransferState.Issued;transfer.Version++;transfer.IssuedAt=DateTimeOffset.UtcNow;
            return Success(transfer.PublicId,"ISSUED",transfer.Version,context);
        },ct);

    public async Task<Result<TransferReceivePlan>> PrepareTransferReceiveAsync(
        TransferReceiveCommand command,IExecutionContext context,CancellationToken ct)
    {
        if(command.Lines.Count==0||command.Lines.Select(x=>x.TransferLinePublicId).Distinct().Count()!=command.Lines.Count)
            return Fail<TransferReceivePlan>("warehouse.transfer.receive_lines","Unique receive lines are required.");
        var transfer=await LockTransfer(context.CompanyId,command.TransferPublicId,ct);
        if(transfer is null)return Fail<TransferReceivePlan>("warehouse.transfer.not_found","Transfer was not found.");
        if(transfer.Version!=command.ExpectedVersion)return Conflict<TransferReceivePlan>("warehouse.transfer.stale","Transfer version is stale.");
        if(transfer.State is not (TransferState.Issued or TransferState.PartiallyReceived or TransferState.ReconciliationRequired))
            return Fail<TransferReceivePlan>("warehouse.transfer.receive_state","Transfer is not receivable.");
        var target=await dbContext.Set<WarehouseRecord>().AsNoTracking().SingleAsync(x=>x.Id==transfer.TargetWarehouseId&&x.CompanyId==context.CompanyId,ct);
        var lines=await dbContext.Set<WarehouseTransferLineRecord>().AsNoTracking().Where(x=>x.TransferId==transfer.Id&&x.CompanyId==context.CompanyId).ToArrayAsync(ct);
        var byId=lines.ToDictionary(x=>x.PublicId);
        var result=new List<TransferReceiveLinePlan>();
        foreach(var input in command.Lines){
            if(input.Quantity<=0m||!byId.TryGetValue(input.TransferLinePublicId,out var l))
                return Fail<TransferReceivePlan>("warehouse.transfer.receive_line","Receive line is invalid.");
            var remaining=l.IssuedQuantity-l.ReceivedQuantity-l.ResolvedLossQuantity;
            if(input.Quantity>remaining)return Fail<TransferReceivePlan>("warehouse.transfer.over_receive","Receive cannot exceed unresolved TRANSIT.");
            var loc=await ActiveLocation(context.CompanyId,target.Id,input.TargetLocationPublicId,ct);
            if(loc is null)return Fail<TransferReceivePlan>("warehouse.transfer.target_location","Target Location is invalid.");
            var ids=await PublicTrade(context.CompanyId,l,ct);
            var lot=l.LotId.HasValue?await dbContext.Set<InventoryLotRecord>().AsNoTracking().SingleAsync(x=>x.Id==l.LotId&&x.CompanyId==context.CompanyId,ct):null;
            var serial=l.SerialId.HasValue?await dbContext.Set<InventorySerialRecord>().AsNoTracking().SingleAsync(x=>x.Id==l.SerialId&&x.CompanyId==context.CompanyId,ct):null;
            var disposition=input.Disposition switch{
                TransferReceiptDisposition.Available=>InventoryDispositionCode.Available,
                TransferReceiptDisposition.Damaged=>InventoryDispositionCode.Damaged,
                _=>InventoryDispositionCode.QualityHold};
            result.Add(new(l.PublicId,ids.Product,ids.Variant,ids.Uom,l.ConversionFactorSnapshot,input.Quantity,
                new InventoryPosition(target.PublicId,null,InventoryDispositionCode.Transit,lot?.PublicId,serial?.PublicId),
                new InventoryPosition(target.PublicId,loc.PublicId,disposition,lot?.PublicId,serial?.PublicId)));
        }
        return Result<TransferReceivePlan>.Success(new(transfer.PublicId,target.PublicId,result));
    }

    public async Task<Result<WarehouseMutationReceipt>> CompleteTransferReceiveAsync(
        Guid id,IReadOnlyList<WarehouseInventoryEffect> effects,string operationKey,IExecutionContext context,CancellationToken ct)=>
        await Mutate("warehouse.transfer.receive",operationKey,"WarehouseTransferReceived","WarehouseTransfer",context,async inner=>{
            var transfer=await LockTransfer(context.CompanyId,id,inner);
            if(transfer is null)return Invalid("warehouse.transfer.not_found","Transfer was not found.");
            var lines=await dbContext.Set<WarehouseTransferLineRecord>().Where(x=>x.CompanyId==context.CompanyId&&x.TransferId==transfer.Id).ToArrayAsync(inner);
            foreach(var effect in effects){
                var line=lines.SingleOrDefault(x=>x.PublicId==effect.WorkLinePublicId);
                if(line is null)return Invalid("warehouse.transfer.receive_effect","Transfer line effect is invalid.");
                var movement=dbContext.Set<InventoryMovementRecord>().Local.SingleOrDefault(x=>x.PublicId==effect.InventoryMovementPublicId)
                    ??await dbContext.Set<InventoryMovementRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.PublicId==effect.InventoryMovementPublicId&&x.CompanyId==context.CompanyId,inner);
                if(movement is null)return Invalid("warehouse.transfer.receive_movement","Inventory movement link is missing.");
                var qty=movement.EnteredQuantity;
                line.ReceivedQuantity+=qty;
                if(movement.TargetDispositionId.HasValue){
                    var d=await dbContext.Set<InventoryDispositionRecord>().AsNoTracking().SingleAsync(x=>x.Id==movement.TargetDispositionId.Value,inner);
                    if(d.Code is InventoryDispositionCode.Damaged or InventoryDispositionCode.QualityHold)
                        line.DamagedReceivedQuantity+=qty;
                }
                dbContext.Add(new WarehouseTransferEffectRecord{
                    PublicId=Guid.NewGuid(),TransferLineId=line.Id,CompanyId=context.CompanyId,
                    InventoryMovementPublicId=effect.InventoryMovementPublicId,EffectKind="RECEIVE",CreatedAt=DateTimeOffset.UtcNow
                });
            }
            var unresolved=lines.Sum(x=>x.IssuedQuantity-x.ReceivedQuantity-x.ResolvedLossQuantity);
            transfer.State=unresolved==0m?TransferState.Received:TransferState.PartiallyReceived;
            transfer.Version++;
            return Success(transfer.PublicId,transfer.State.ToString().ToUpperInvariant(),transfer.Version,context);
        },ct);

    public async Task<Result<TransferLossPlan>> PrepareTransferLossAsync(
        Guid id,Guid lineId,decimal quantity,string reason,IExecutionContext context,CancellationToken ct)
    {
        if(quantity<=0m||string.IsNullOrWhiteSpace(reason))return Fail<TransferLossPlan>("warehouse.transfer.loss_invalid","Positive quantity and reason are required.");
        var transfer=await LockTransfer(context.CompanyId,id,ct);
        if(transfer is null)return Fail<TransferLossPlan>("warehouse.transfer.not_found","Transfer was not found.");
        if(transfer.State is not (TransferState.Issued or TransferState.PartiallyReceived or TransferState.ReconciliationRequired))
            return Fail<TransferLossPlan>("warehouse.transfer.loss_state","Transfer is not in an unresolved transit state.");
        var line=await dbContext.Set<WarehouseTransferLineRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.TransferId==transfer.Id&&x.PublicId==lineId,ct);
        if(line is null)return Fail<TransferLossPlan>("warehouse.transfer.line","Transfer line was not found.");
        if(quantity>line.IssuedQuantity-line.ReceivedQuantity-line.ResolvedLossQuantity)
            return Fail<TransferLossPlan>("warehouse.transfer.loss_over","Loss cannot exceed unresolved TRANSIT.");
        var target=await dbContext.Set<WarehouseRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==transfer.TargetWarehouseId,ct);
        var ids=await PublicTrade(context.CompanyId,line,ct);
        var lot=line.LotId.HasValue?await dbContext.Set<InventoryLotRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==line.LotId,ct):null;
        var serial=line.SerialId.HasValue?await dbContext.Set<InventorySerialRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==line.SerialId,ct):null;
        return Result<TransferLossPlan>.Success(new(
            transfer.PublicId,line.PublicId,target.PublicId,transfer.CreatorActorId,transfer.Version,
            ids.Product,ids.Variant,ids.Uom,line.ConversionFactorSnapshot,quantity,
            new InventoryPosition(target.PublicId,null,InventoryDispositionCode.Transit,lot?.PublicId,serial?.PublicId)));
    }

    public async Task<Result<WarehouseMutationReceipt>> MarkTransferLossApprovalAsync(
        Guid id,Guid lineId,Guid approvalId,IExecutionContext context,CancellationToken ct)
    {
        var transfer=await dbContext.Set<WarehouseTransferRecord>().SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==id,ct);
        if(transfer is null)return Invalid("warehouse.transfer.not_found","Transfer was not found.");
        transfer.State=TransferState.ReconciliationRequired;transfer.Version++;
        return Success(transfer.PublicId,"RECONCILIATION_REQUIRED",transfer.Version,context);
    }

    public async Task<Result<WarehouseMutationReceipt>> CompleteTransferLossAsync(
        Guid id,Guid lineId,decimal quantity,Guid movementId,string operationKey,IExecutionContext context,CancellationToken ct)=>
        await Mutate("warehouse.transfer.loss",operationKey,"WarehouseTransferLossAdjusted","WarehouseTransfer",context,async inner=>{
            var transfer=await LockTransfer(context.CompanyId,id,inner);
            if(transfer is null)return Invalid("warehouse.transfer.not_found","Transfer was not found.");
            var line=await dbContext.Set<WarehouseTransferLineRecord>().SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.TransferId==transfer.Id&&x.PublicId==lineId,inner);
            if(line is null||quantity<=0m||quantity>line.IssuedQuantity-line.ReceivedQuantity-line.ResolvedLossQuantity)
                return Invalid("warehouse.transfer.loss_invalid","Loss quantity is invalid.");
            line.ResolvedLossQuantity+=quantity;
            dbContext.Add(new WarehouseTransferEffectRecord{
                PublicId=Guid.NewGuid(),TransferLineId=line.Id,CompanyId=context.CompanyId,
                InventoryMovementPublicId=movementId,EffectKind="LOSS",CreatedAt=DateTimeOffset.UtcNow
            });
            var lines=await dbContext.Set<WarehouseTransferLineRecord>().Where(x=>x.CompanyId==context.CompanyId&&x.TransferId==transfer.Id).ToArrayAsync(inner);
            var unresolved=lines.Sum(x=>x.IssuedQuantity-x.ReceivedQuantity-x.ResolvedLossQuantity);
            transfer.State=unresolved==0m?TransferState.Closed:TransferState.ReconciliationRequired;
            transfer.Version++; if(transfer.State==TransferState.Closed)transfer.ClosedAt=DateTimeOffset.UtcNow;
            return Success(transfer.PublicId,transfer.State.ToString().ToUpperInvariant(),transfer.Version,context);
        },ct);

    public async Task<Result<WarehouseMutationReceipt>> CloseTransferAsync(
        Guid id,long expectedVersion,string operationKey,IExecutionContext context,CancellationToken ct)=>
        await Mutate("warehouse.transfer.close",operationKey,"WarehouseTransferClosed","WarehouseTransfer",context,async inner=>{
            var transfer=await LockTransfer(context.CompanyId,id,inner);
            if(transfer is null)return Invalid("warehouse.transfer.not_found","Transfer was not found.");
            if(transfer.Version!=expectedVersion)return ConflictReceipt("warehouse.transfer.stale","Transfer version is stale.");
            var lines=await dbContext.Set<WarehouseTransferLineRecord>().AsNoTracking()
                .Where(x=>x.CompanyId==context.CompanyId&&x.TransferId==transfer.Id).ToArrayAsync(inner);
            var unresolved=lines.Sum(x=>x.IssuedQuantity-x.ReceivedQuantity-x.ResolvedLossQuantity);
            if(unresolved!=0m)return Invalid("warehouse.transfer.unresolved_transit","Transfer cannot close while TRANSIT remains unresolved.");
            if(transfer.State is not (TransferState.Received or TransferState.ReconciliationRequired))
                return Invalid("warehouse.transfer.close_state","Transfer is not close-eligible.");
            transfer.State=TransferState.Closed;transfer.Version++;transfer.ClosedAt=DateTimeOffset.UtcNow;
            return Success(transfer.PublicId,"CLOSED",transfer.Version,context);
        },ct);

    public async Task<Result<TransferReversePlan>> PrepareTransferReverseAsync(
        Guid id,long expectedVersion,IExecutionContext context,CancellationToken ct)
    {
        var transfer=await LockTransfer(context.CompanyId,id,ct);
        if(transfer is null)return Fail<TransferReversePlan>("warehouse.transfer.not_found","Transfer was not found.");
        if(transfer.Version!=expectedVersion)return Conflict<TransferReversePlan>("warehouse.transfer.stale","Transfer version is stale.");
        if(transfer.State is TransferState.Draft or TransferState.Cancelled or TransferState.Reversed)
            return Fail<TransferReversePlan>("warehouse.transfer.reverse_state","Transfer has no posted physical effect to reverse.");

        var source=await dbContext.Set<WarehouseRecord>().AsNoTracking()
            .SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==transfer.SourceWarehouseId,ct);
        var target=await dbContext.Set<WarehouseRecord>().AsNoTracking()
            .SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==transfer.TargetWarehouseId,ct);
        var lines=await dbContext.Set<WarehouseTransferLineRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&x.TransferId==transfer.Id).ToArrayAsync(ct);
        var lineIds=lines.Select(x=>x.Id).ToArray();
        var effects=await dbContext.Set<WarehouseTransferEffectRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lineIds.Contains(x.TransferLineId))
            .OrderByDescending(x=>x.Id).ToArrayAsync(ct);
        if(effects.Any(x=>x.EffectKind=="LOSS"))
            return Fail<TransferReversePlan>("warehouse.transfer.reverse_loss",
                "Transfer with posted loss adjustment requires a later Finance-aware correction workflow.");

        var result=new List<TransferReverseLinePlan>();
        foreach(var effect in effects.Where(x=>x.EffectKind is "RECEIVE" or "ISSUE"))
        {
            var line=lines.Single(x=>x.Id==effect.TransferLineId);
            var movement=await dbContext.Set<InventoryMovementRecord>().AsNoTracking()
                .SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==effect.InventoryMovementPublicId,ct);
            var product=await dbContext.Set<ProductRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==movement.ProductId,ct);
            var variant=movement.VariantId.HasValue
                ? await dbContext.Set<ProductVariantRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==movement.VariantId,ct)
                : null;
            var uom=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==movement.UomId,ct);
            var lot=movement.LotId.HasValue
                ? await dbContext.Set<InventoryLotRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==movement.LotId,ct)
                : null;
            var serial=movement.SerialId.HasValue
                ? await dbContext.Set<InventorySerialRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==movement.SerialId,ct)
                : null;

            InventoryPosition? originalSource=null,originalTarget=null;
            if(movement.SourceWarehouseId.HasValue)
            {
                var wh=await dbContext.Set<WarehouseRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==movement.SourceWarehouseId,ct);
                var loc=movement.SourceLocationId.HasValue
                    ? await dbContext.Set<LocationRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==movement.SourceLocationId,ct)
                    : null;
                var disp=movement.SourceDispositionId.HasValue
                    ? await dbContext.Set<InventoryDispositionRecord>().AsNoTracking().SingleAsync(x=>x.Id==movement.SourceDispositionId,ct)
                    : null;
                originalSource=new InventoryPosition(wh.PublicId,loc?.PublicId,disp!.Code,lot?.PublicId,serial?.PublicId);
            }
            if(movement.TargetWarehouseId.HasValue)
            {
                var wh=await dbContext.Set<WarehouseRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==movement.TargetWarehouseId,ct);
                var loc=movement.TargetLocationId.HasValue
                    ? await dbContext.Set<LocationRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==movement.TargetLocationId,ct)
                    : null;
                var disp=movement.TargetDispositionId.HasValue
                    ? await dbContext.Set<InventoryDispositionRecord>().AsNoTracking().SingleAsync(x=>x.Id==movement.TargetDispositionId,ct)
                    : null;
                originalTarget=new InventoryPosition(wh.PublicId,loc?.PublicId,disp!.Code,lot?.PublicId,serial?.PublicId);
            }
            if(originalTarget is null)
                return Fail<TransferReversePlan>("warehouse.transfer.reverse_shape","Transfer movement target is missing.");

            result.Add(new TransferReverseLinePlan(
                line.PublicId,product.PublicId,variant?.PublicId,uom.PublicId,movement.ConversionFactorSnapshot,
                movement.EnteredQuantity,originalTarget,originalSource!,movement.PublicId));
        }
        if(result.Count==0)return Fail<TransferReversePlan>("warehouse.transfer.reverse_effects","No posted Transfer effects were found.");
        return Result<TransferReversePlan>.Success(new(transfer.PublicId,source.PublicId,target.PublicId,result));
    }

    public async Task<Result<WarehouseMutationReceipt>> CompleteTransferReverseAsync(
        Guid id,IReadOnlyList<WarehouseInventoryEffect> effects,string operationKey,IExecutionContext context,CancellationToken ct)=>
        await Mutate("warehouse.transfer.reverse",operationKey,"WarehouseTransferReversed","WarehouseTransfer",context,async inner=>{
            var transfer=await LockTransfer(context.CompanyId,id,inner);
            if(transfer is null||transfer.State!=TransferState.Issued)return Invalid("warehouse.transfer.reverse_state","Transfer is not reversible.");
            var lines=await dbContext.Set<WarehouseTransferLineRecord>().Where(x=>x.CompanyId==context.CompanyId&&x.TransferId==transfer.Id).ToArrayAsync(inner);
            foreach(var effect in effects){
                var line=lines.SingleOrDefault(x=>x.PublicId==effect.WorkLinePublicId);
                if(line is null)return Invalid("warehouse.transfer.reverse_effect","Transfer reversal effect is invalid.");
                dbContext.Add(new WarehouseTransferEffectRecord{
                    PublicId=Guid.NewGuid(),TransferLineId=line.Id,CompanyId=context.CompanyId,
                    InventoryMovementPublicId=effect.InventoryMovementPublicId,EffectKind="REVERSE",CreatedAt=DateTimeOffset.UtcNow
                });
            }
            transfer.State=TransferState.Reversed;transfer.Version++;transfer.ClosedAt=DateTimeOffset.UtcNow;
            return Success(transfer.PublicId,"REVERSED",transfer.Version,context);
        },ct);

    public async Task<Result<WarehouseMutationReceipt>> CreateCountAsync(
        CreateCountCommand command,IExecutionContext context,CancellationToken ct)=>
        await Mutate("warehouse.count.create",command.OperationKey,"WarehouseCountCreated","StockCount",context,async inner=>{
            if(string.IsNullOrWhiteSpace(command.Number)||command.LocationPublicIds.Count==0||command.LocationPublicIds.Distinct().Count()!=command.LocationPublicIds.Count)
                return Invalid("warehouse.count.invalid","Count number and unique Location scope are required.");
            var wh=await ActiveWarehouse(context.CompanyId,command.WarehousePublicId,inner);
            if(wh is null)return Invalid("warehouse.count.warehouse","Warehouse must be ACTIVE.");
            var locations=await dbContext.Set<LocationRecord>().AsNoTracking()
                .Where(x=>x.CompanyId==context.CompanyId&&x.WarehouseId==wh.Id&&command.LocationPublicIds.Contains(x.PublicId)&&x.State==InventoryMasterState.Active&&x.StockBearing)
                .ToArrayAsync(inner);
            if(locations.Length!=command.LocationPublicIds.Count)return Invalid("warehouse.count.locations","All count Locations must be ACTIVE stock-bearing locations in Warehouse.");
            var count=new StockCountSessionRecord{
                PublicId=Guid.NewGuid(),CompanyId=context.CompanyId,Number=command.Number.Trim(),WarehouseId=wh.Id,
                State=StockCountState.Draft,Version=1,CreatorActorId=context.ActorId,CreatedAt=DateTimeOffset.UtcNow
            };
            dbContext.Add(count);await dbContext.SaveChangesAsync(inner);
            foreach(var l in locations)dbContext.Add(new StockCountScopeRecord{CountSessionId=count.Id,CompanyId=context.CompanyId,LocationId=l.Id});
            return Success(count.PublicId,"DRAFT",count.Version,context);
        },ct);

    public async Task<Guid?> GetCountWarehouseAsync(Guid companyId,Guid countPublicId,CancellationToken ct)
    {
        var row=await dbContext.Set<StockCountSessionRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==countPublicId,ct);
        if(row is null)return null;
        return await dbContext.Set<WarehouseRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&x.Id==row.WarehouseId)
            .Select(x=>(Guid?)x.PublicId).SingleAsync(ct);
    }

    public async Task<Result<WarehouseMutationReceipt>> StartCountAsync(
        Guid id,long expectedVersion,string operationKey,IExecutionContext context,CancellationToken ct)=>
        await Mutate("warehouse.count.start",operationKey,"WarehouseCountStarted","StockCount",context,async inner=>{
            var count=await LockCount(context.CompanyId,id,inner);
            if(count is null)return Invalid("warehouse.count.not_found","Count was not found.");
            if(count.Version!=expectedVersion)return ConflictReceipt("warehouse.count.stale","Count version is stale.");
            if(count.State!=StockCountState.Draft)return Invalid("warehouse.count.state","Only DRAFT Count can start.");
            var snapshot=await dbContext.Set<InventoryMovementRecord>().Where(x=>x.CompanyId==context.CompanyId).MaxAsync(x=>(long?)x.Id,inner)??0;
            var scope=await dbContext.Set<StockCountScopeRecord>().AsNoTracking().Where(x=>x.CompanyId==context.CompanyId&&x.CountSessionId==count.Id).Select(x=>x.LocationId).ToArrayAsync(inner);
            var targetMovements=await dbContext.Set<InventoryMovementRecord>().AsNoTracking()
                .Where(x=>x.CompanyId==context.CompanyId&&x.Id<=snapshot&&x.TargetLocationId.HasValue&&scope.Contains(x.TargetLocationId.Value))
                .Select(x=>new{x.ProductId,x.VariantId,x.TargetLocationId,DispositionId=x.TargetDispositionId,x.LotId,x.SerialId,x.BaseQuantity}).ToArrayAsync(inner);
            var sourceMovements=await dbContext.Set<InventoryMovementRecord>().AsNoTracking()
                .Where(x=>x.CompanyId==context.CompanyId&&x.Id<=snapshot&&x.SourceLocationId.HasValue&&scope.Contains(x.SourceLocationId.Value))
                .Select(x=>new{x.ProductId,x.VariantId,x.SourceLocationId,DispositionId=x.SourceDispositionId,x.LotId,x.SerialId,x.BaseQuantity}).ToArrayAsync(inner);
            var keys=targetMovements.Select(x=>(x.ProductId,x.VariantId,Location:x.TargetLocationId!.Value,Disposition:x.DispositionId!.Value,x.LotId,x.SerialId))
                .Concat(sourceMovements.Select(x=>(x.ProductId,x.VariantId,Location:x.SourceLocationId!.Value,Disposition:x.DispositionId!.Value,x.LotId,x.SerialId))).Distinct().ToArray();
            foreach(var k in keys){
                var expected=targetMovements.Where(x=>x.ProductId==k.ProductId&&x.VariantId==k.VariantId&&x.TargetLocationId==k.Location&&x.DispositionId==k.Disposition&&x.LotId==k.LotId&&x.SerialId==k.SerialId).Sum(x=>x.BaseQuantity)
                    -sourceMovements.Where(x=>x.ProductId==k.ProductId&&x.VariantId==k.VariantId&&x.SourceLocationId==k.Location&&x.DispositionId==k.Disposition&&x.LotId==k.LotId&&x.SerialId==k.SerialId).Sum(x=>x.BaseQuantity);
                if(expected<=0m)continue;
                var baseUom=await dbContext.Set<ProductUomRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==context.CompanyId&&x.ProductId==k.ProductId&&x.VariantId==k.VariantId&&x.Role==ProductUomRole.Base&&x.State==ProductMasterRecordState.Active)
                    .OrderByDescending(x=>x.Id).FirstOrDefaultAsync(inner)
                    ??await dbContext.Set<ProductUomRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==context.CompanyId&&x.ProductId==k.ProductId&&x.VariantId==null&&x.Role==ProductUomRole.Base&&x.State==ProductMasterRecordState.Active)
                    .OrderByDescending(x=>x.Id).FirstOrDefaultAsync(inner);
                if(baseUom is null)return Invalid("warehouse.count.base_uom","Count requires an active BASE Product-UOM.");
                dbContext.Add(new StockCountLineRecord{
                    PublicId=Guid.NewGuid(),CountSessionId=count.Id,CompanyId=context.CompanyId,
                    ProductId=k.ProductId,VariantId=k.VariantId,UomId=baseUom.UomId,ConversionFactorSnapshot=baseUom.ConversionFactor,
                    LocationId=k.Location,DispositionId=k.Disposition,LotId=k.LotId,SerialId=k.SerialId,
                    ExpectedStartQuantity=expected,NetInterveningQuantity=0,ExpectedReconciliationQuantity=expected,DiscrepancyQuantity=0
                });
            }
            count.SnapshotMovementId=snapshot;count.State=StockCountState.Counting;count.Version++;count.StartedAt=DateTimeOffset.UtcNow;
            return Success(count.PublicId,"COUNTING",count.Version,context);
        },ct);

    public async Task<Result<WarehouseMutationReceipt>> RecordCountAsync(
        RecordCountObservationCommand command,IExecutionContext context,CancellationToken ct)=>
        await Mutate("warehouse.count.observe",command.OperationKey,"WarehouseCountObserved","StockCount",context,async inner=>{
            var count=await LockCount(context.CompanyId,command.CountPublicId,inner);
            if(count is null)return Invalid("warehouse.count.not_found","Count was not found.");
            if(count.Version!=command.ExpectedVersion)return ConflictReceipt("warehouse.count.stale","Count version is stale.");
            if(count.State!=StockCountState.Counting)return Invalid("warehouse.count.state","Count is not COUNTING.");
            if(command.Observations.Count==0||command.Observations.Select(x=>x.CountLinePublicId).Distinct().Count()!=command.Observations.Count||command.Observations.Any(x=>x.CountedQuantity<0m))
                return Invalid("warehouse.count.observation","Unique non-negative observations are required.");
            var lines=await dbContext.Set<StockCountLineRecord>().Where(x=>x.CompanyId==context.CompanyId&&x.CountSessionId==count.Id).ToArrayAsync(inner);
            foreach(var input in command.Observations){
                var line=lines.SingleOrDefault(x=>x.PublicId==input.CountLinePublicId);
                if(line is null)return Invalid("warehouse.count.line","Count line was not found.");
                if(!command.IsRecount&&line.AcceptedCountQuantity.HasValue)return Invalid("warehouse.count.duplicate_first","First count observation already exists.");
                dbContext.Add(new StockCountObservationRecord{
                    PublicId=Guid.NewGuid(),CountLineId=line.Id,CompanyId=context.CompanyId,CountedQuantity=input.CountedQuantity,
                    IsRecount=command.IsRecount,ActorId=context.ActorId,CreatedAt=DateTimeOffset.UtcNow
                });
                line.AcceptedCountQuantity=input.CountedQuantity;
            }
            count.Version++;
            return Success(count.PublicId,"COUNTING",count.Version,context);
        },ct);

    public async Task<Result<CountAdjustmentPlan>> ReviewCountAsync(
        Guid id,long expectedVersion,string operationKey,IExecutionContext context,CancellationToken ct)
    {
        var count=await LockCount(context.CompanyId,id,ct);
        if(count is null)return Fail<CountAdjustmentPlan>("warehouse.count.not_found","Count was not found.");
        if(count.Version!=expectedVersion)return Conflict<CountAdjustmentPlan>("warehouse.count.stale","Count version is stale.");
        if(count.State!=StockCountState.Counting)return Fail<CountAdjustmentPlan>("warehouse.count.state","Count is not COUNTING.");
        var lines=await dbContext.Set<StockCountLineRecord>().Where(x=>x.CompanyId==context.CompanyId&&x.CountSessionId==count.Id).ToArrayAsync(ct);
        if(lines.Any(x=>!x.AcceptedCountQuantity.HasValue))return Fail<CountAdjustmentPlan>("warehouse.count.incomplete","Every Count line requires an observation.");
        var snapshot=count.SnapshotMovementId??0;
        foreach(var line in lines){
            var net=await NetMovementAfter(context.CompanyId,line,snapshot,ct);
            line.NetInterveningQuantity=net;
            line.ExpectedReconciliationQuantity=line.ExpectedStartQuantity+net;
            line.DiscrepancyQuantity=line.AcceptedCountQuantity!.Value-line.ExpectedReconciliationQuantity;
        }
        count.ReviewedAt=DateTimeOffset.UtcNow;
        count.State=lines.Any(x=>x.DiscrepancyQuantity!=0m)?StockCountState.PendingApproval:StockCountState.Closed;
        count.Version++;
        await dbContext.SaveChangesAsync(ct);
        return await BuildCountPlan(count,lines,context.CompanyId,ct);
    }

    public async Task<Result<CountAdjustmentPlan>> GetCountPostPlanAsync(Guid id,IExecutionContext context,CancellationToken ct)
    {
        var count=await dbContext.Set<StockCountSessionRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==id,ct);
        if(count is null)return Fail<CountAdjustmentPlan>("warehouse.count.not_found","Count was not found.");
        if(count.State is not (StockCountState.PendingApproval or StockCountState.Approved))
            return Fail<CountAdjustmentPlan>("warehouse.count.post_state","Count is not approval/post eligible.");
        var lines=await dbContext.Set<StockCountLineRecord>().AsNoTracking().Where(x=>x.CompanyId==context.CompanyId&&x.CountSessionId==count.Id).ToArrayAsync(ct);
        return await BuildCountPlan(count,lines,context.CompanyId,ct);
    }

    public async Task<Result<WarehouseMutationReceipt>> MarkCountApprovalAsync(
        Guid id,Guid approvalId,IExecutionContext context,CancellationToken ct)
    {
        var count=await LockCount(context.CompanyId,id,ct);
        if(count is null||count.State!=StockCountState.PendingApproval)return Invalid("warehouse.count.approval_state","Count is not pending approval.");
        count.ApprovalDecisionPublicId=approvalId;count.State=StockCountState.Approved;count.Version++;
        await dbContext.SaveChangesAsync(ct);
        return Success(count.PublicId,"APPROVED",count.Version,context);
    }

    public async Task<Result<WarehouseMutationReceipt>> CompleteCountPostAsync(
        Guid id,IReadOnlyList<WarehouseInventoryEffect> effects,string operationKey,IExecutionContext context,CancellationToken ct)=>
        await Mutate("warehouse.count.post",operationKey,"WarehouseCountAdjustmentPosted","StockCount",context,async inner=>{
            var count=await LockCount(context.CompanyId,id,inner);
            if(count is null||count.State!=StockCountState.Approved)return Invalid("warehouse.count.post_state","Count must be APPROVED.");
            var lines=await dbContext.Set<StockCountLineRecord>().Where(x=>x.CompanyId==context.CompanyId&&x.CountSessionId==count.Id).ToArrayAsync(inner);
            foreach(var effect in effects){
                var line=lines.SingleOrDefault(x=>x.PublicId==effect.WorkLinePublicId);
                if(line is null||line.DiscrepancyQuantity>=0m)return Invalid("warehouse.count.effect","Count effect must reference a negative discrepancy.");
                dbContext.Add(new StockCountEffectRecord{
                    PublicId=Guid.NewGuid(),CountLineId=line.Id,CompanyId=context.CompanyId,
                    InventoryMovementPublicId=effect.InventoryMovementPublicId,IsReversal=false,CreatedAt=DateTimeOffset.UtcNow
                });
            }
            count.State=StockCountState.Posted;count.Version++;count.PostedAt=DateTimeOffset.UtcNow;
            return Success(count.PublicId,"POSTED",count.Version,context);
        },ct);

    public async Task<Result<WarehouseMutationReceipt>> RequestScrapAsync(
        ScrapRequestCommand command,IExecutionContext context,CancellationToken ct)=>
        await Mutate("warehouse.scrap.request",command.OperationKey,"WarehouseScrapRequested","WarehouseScrap",context,async inner=>{
            if(string.IsNullOrWhiteSpace(command.Number)||string.IsNullOrWhiteSpace(command.Reason)||command.Quantity<=0m||command.ConversionFactorSnapshot<=0m)
                return Invalid("warehouse.scrap.invalid","Number, reason and positive quantity/conversion are required.");
            var trade=await ResolveTrade(context.CompanyId,command.ProductPublicId,command.VariantPublicId,command.UomPublicId,inner);
            if(trade is null)return Invalid("warehouse.scrap.trade","Product/Variant/UOM identity is invalid.");
            var pos=await ResolvePosition(context.CompanyId,command.Source,trade,inner);
            if(pos is null)return Invalid("warehouse.scrap.position","Scrap source position is invalid.");
            var disposition=await dbContext.Set<InventoryDispositionRecord>().AsNoTracking().SingleAsync(x=>x.Code==command.Source.Disposition,inner);
            var row=new WarehouseScrapRecord{
                PublicId=Guid.NewGuid(),CompanyId=context.CompanyId,Number=command.Number.Trim(),WarehouseId=pos.Value.WarehouseId,
                LocationId=pos.Value.LocationId,ProductId=trade.ProductId,VariantId=trade.VariantId,UomId=trade.UomId,
                ConversionFactorSnapshot=command.ConversionFactorSnapshot,DispositionId=disposition.Id,LotId=pos.Value.LotId,SerialId=pos.Value.SerialId,
                Quantity=command.Quantity,Reason=command.Reason.Trim(),State=ScrapState.PendingApproval,Version=1,
                CreatorActorId=context.ActorId,CreatedAt=DateTimeOffset.UtcNow
            };
            dbContext.Add(row);
            return Success(row.PublicId,"PENDING_APPROVAL",row.Version,context);
        },ct);

    public async Task<Result<ScrapPostPlan>> GetScrapPostPlanAsync(Guid id,IExecutionContext context,CancellationToken ct)
    {
        var s=await dbContext.Set<WarehouseScrapRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==id,ct);
        if(s is null)return Fail<ScrapPostPlan>("warehouse.scrap.not_found","Scrap request was not found.");
        if(s.State is not (ScrapState.PendingApproval or ScrapState.Approved))return Fail<ScrapPostPlan>("warehouse.scrap.state","Scrap is not approval/post eligible.");
        var wh=await dbContext.Set<WarehouseRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==s.WarehouseId,ct);
        var loc=s.LocationId.HasValue?await dbContext.Set<LocationRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==s.LocationId,ct):null;
        var p=await dbContext.Set<ProductRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==s.ProductId,ct);
        var v=s.VariantId.HasValue?await dbContext.Set<ProductVariantRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==s.VariantId,ct):null;
        var u=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==s.UomId,ct);
        var d=await dbContext.Set<InventoryDispositionRecord>().AsNoTracking().SingleAsync(x=>x.Id==s.DispositionId,ct);
        var lot=s.LotId.HasValue?await dbContext.Set<InventoryLotRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==s.LotId,ct):null;
        var serial=s.SerialId.HasValue?await dbContext.Set<InventorySerialRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==s.SerialId,ct):null;
        return Result<ScrapPostPlan>.Success(new(
            s.PublicId,wh.PublicId,s.CreatorActorId,s.Version,p.PublicId,v?.PublicId,u.PublicId,s.Quantity,s.ConversionFactorSnapshot,
            new InventoryPosition(wh.PublicId,loc?.PublicId,d.Code,lot?.PublicId,serial?.PublicId)));
    }

    public async Task<Result<WarehouseMutationReceipt>> MarkScrapApprovalAsync(
        Guid id,Guid approvalId,IExecutionContext context,CancellationToken ct)
    {
        var s=await dbContext.Set<WarehouseScrapRecord>().SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==id,ct);
        if(s is null||s.State!=ScrapState.PendingApproval)return Invalid("warehouse.scrap.approval_state","Scrap is not pending approval.");
        s.ApprovalDecisionPublicId=approvalId;s.State=ScrapState.Approved;s.Version++;
        await dbContext.SaveChangesAsync(ct);
        return Success(s.PublicId,"APPROVED",s.Version,context);
    }

    public async Task<Result<WarehouseMutationReceipt>> CompleteScrapPostAsync(
        Guid id,Guid movementId,string operationKey,IExecutionContext context,CancellationToken ct)=>
        await Mutate("warehouse.scrap.post",operationKey,"WarehouseScrapPosted","WarehouseScrap",context,async inner=>{
            var s=await dbContext.Set<WarehouseScrapRecord>().SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==id,inner);
            if(s is null||s.State!=ScrapState.Approved)return Invalid("warehouse.scrap.post_state","Scrap must be APPROVED.");
            s.InventoryMovementPublicId=movementId;s.State=ScrapState.Posted;s.Version++;s.PostedAt=DateTimeOffset.UtcNow;
            return Success(s.PublicId,"POSTED",s.Version,context);
        },ct);

    public async Task<Result<WarehouseMutationReceipt>> RecordOfflineAsync(
        OfflineOperationCommand command,IExecutionContext context,CancellationToken ct)
    {
        if(command.ClientOperationId==Guid.Empty||string.IsNullOrWhiteSpace(command.OperationType)||string.IsNullOrWhiteSpace(command.ScanIdentity))
            return Invalid("warehouse.offline.invalid","Client operation id, type and scan identity are required.");
        var existing=await dbContext.Set<WarehouseOfflineOperationRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.ClientOperationId==command.ClientOperationId,ct);
        if(existing is not null)
            return Success(existing.PublicId,existing.State.ToString().ToUpperInvariant(),1,context);
        return await Mutate("warehouse.offline",command.OperationKey,"WarehouseOfflineOperationRecorded","WarehouseOfflineOperation",context,async inner=>{
            var wh=await ActiveWarehouse(context.CompanyId,command.WarehousePublicId,inner);
            if(wh is null)return Invalid("warehouse.offline.warehouse","Warehouse must be ACTIVE.");
            var row=new WarehouseOfflineOperationRecord{
                PublicId=Guid.NewGuid(),CompanyId=context.CompanyId,ClientOperationId=command.ClientOperationId,WarehouseId=wh.Id,
                OperationType=command.OperationType.Trim(),WorkPublicId=command.WorkPublicId,ExpectedVersion=command.ExpectedVersion,
                ScanIdentity=command.ScanIdentity.Trim(),LocalTimestamp=command.LocalTimestamp,State=OfflineOperationState.Pending,
                ActorId=context.ActorId,CorrelationId=context.CorrelationId.Value,CreatedAt=DateTimeOffset.UtcNow
            };
            dbContext.Add(row);
            return Success(row.PublicId,"PENDING",1,context);
        },ct);
    }

    public async Task<bool> HasOpenWarehouseWorkAsync(Guid companyId,Guid warehousePublicId,CancellationToken ct)
    {
        var wh=await dbContext.Set<WarehouseRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==warehousePublicId,ct);
        if(wh is null)return false;
        return await dbContext.Set<PickWorkRecord>().AsNoTracking().AnyAsync(x=>x.CompanyId==companyId&&x.WarehouseId==wh.Id&&x.State is not (PickWorkState.Closed or PickWorkState.Cancelled),ct)
            ||await dbContext.Set<WarehouseTransferRecord>().AsNoTracking().AnyAsync(x=>x.CompanyId==companyId&&(x.SourceWarehouseId==wh.Id||x.TargetWarehouseId==wh.Id)&&x.State is not (TransferState.Closed or TransferState.Cancelled or TransferState.Reversed),ct)
            ||await dbContext.Set<StockCountSessionRecord>().AsNoTracking().AnyAsync(x=>x.CompanyId==companyId&&x.WarehouseId==wh.Id&&x.State is not (StockCountState.Closed or StockCountState.Cancelled or StockCountState.Reversed),ct)
            ||await dbContext.Set<WarehouseScrapRecord>().AsNoTracking().AnyAsync(x=>x.CompanyId==companyId&&x.WarehouseId==wh.Id&&x.State is not (ScrapState.Posted or ScrapState.Cancelled or ScrapState.Reversed),ct);
    }

    public async Task<bool> HasOpenLocationWorkAsync(Guid companyId,Guid locationPublicId,CancellationToken ct)
    {
        var loc=await dbContext.Set<LocationRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==locationPublicId,ct);
        if(loc is null)return false;
        return await dbContext.Set<PickWorkRecord>().AsNoTracking().AnyAsync(x=>x.CompanyId==companyId&&x.LocationId==loc.Id&&x.State is not (PickWorkState.Closed or PickWorkState.Cancelled),ct)
            ||await dbContext.Set<WarehouseTransferLineRecord>().AsNoTracking().AnyAsync(x=>x.CompanyId==companyId&&(x.SourceLocationId==loc.Id||x.TargetLocationId==loc.Id),ct)
            ||await dbContext.Set<StockCountScopeRecord>().AsNoTracking().AnyAsync(x=>x.CompanyId==companyId&&x.LocationId==loc.Id,ct);
    }

    private async Task<IReadOnlyList<WarehouseWorkListItem>> ListOperations(Guid companyId,CancellationToken ct)
    {
        var rows=await dbContext.Set<WarehouseOperationRecord>().AsNoTracking().Where(x=>x.CompanyId==companyId)
            .OrderByDescending(x=>x.CreatedAt).Take(250).ToArrayAsync(ct);
        var wh=await Map<WarehouseRecord>(companyId,rows.Select(x=>x.WarehouseId),x=>x.Id,ct);
        return rows.Select(x=>new WarehouseWorkListItem(x.PublicId,x.SourceDocumentPublicId?.ToString("D")??x.PublicId.ToString("D"),
            x.Kind.ToString().ToUpperInvariant(),x.State.ToString().ToUpperInvariant(),wh[x.WarehouseId].PublicId,x.Version,x.CreatedAt)).ToArray();
    }

    private async Task<Result<CountAdjustmentPlan>> BuildCountPlan(
        StockCountSessionRecord count,IReadOnlyList<StockCountLineRecord> lines,Guid companyId,CancellationToken ct)
    {
        var wh=await dbContext.Set<WarehouseRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==companyId&&x.Id==count.WarehouseId,ct);
        var result=new List<CountAdjustmentPlanLine>();
        foreach(var l in lines){
            var p=await dbContext.Set<ProductRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==companyId&&x.Id==l.ProductId,ct);
            var v=l.VariantId.HasValue?await dbContext.Set<ProductVariantRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==companyId&&x.Id==l.VariantId,ct):null;
            var u=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==companyId&&x.Id==l.UomId,ct);
            var loc=await dbContext.Set<LocationRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==companyId&&x.Id==l.LocationId,ct);
            var d=await dbContext.Set<InventoryDispositionRecord>().AsNoTracking().SingleAsync(x=>x.Id==l.DispositionId,ct);
            var lot=l.LotId.HasValue?await dbContext.Set<InventoryLotRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==companyId&&x.Id==l.LotId,ct):null;
            var serial=l.SerialId.HasValue?await dbContext.Set<InventorySerialRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==companyId&&x.Id==l.SerialId,ct):null;
            result.Add(new(l.PublicId,p.PublicId,v?.PublicId,u.PublicId,l.ConversionFactorSnapshot,l.DiscrepancyQuantity,
                new InventoryPosition(wh.PublicId,loc.PublicId,d.Code,lot?.PublicId,serial?.PublicId)));
        }
        var counterActors=await (
            from o in dbContext.Set<StockCountObservationRecord>().AsNoTracking()
            join l in dbContext.Set<StockCountLineRecord>().AsNoTracking() on o.CountLineId equals l.Id
            where o.CompanyId==companyId&&l.CompanyId==companyId&&l.CountSessionId==count.Id
            select o.ActorId).Distinct().ToArrayAsync(ct);
        return Result<CountAdjustmentPlan>.Success(new(
            count.PublicId,wh.PublicId,count.CreatorActorId,counterActors,count.Version,result));
    }

    private async Task<decimal> NetMovementAfter(Guid companyId,StockCountLineRecord line,long snapshot,CancellationToken ct)
    {
        var incoming=await dbContext.Set<InventoryMovementRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&x.Id>snapshot&&x.ProductId==line.ProductId&&x.VariantId==line.VariantId&&
                x.TargetLocationId==line.LocationId&&x.TargetDispositionId==line.DispositionId&&x.LotId==line.LotId&&x.SerialId==line.SerialId)
            .SumAsync(x=>(decimal?)x.BaseQuantity,ct)??0m;
        var outgoing=await dbContext.Set<InventoryMovementRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&x.Id>snapshot&&x.ProductId==line.ProductId&&x.VariantId==line.VariantId&&
                x.SourceLocationId==line.LocationId&&x.SourceDispositionId==line.DispositionId&&x.LotId==line.LotId&&x.SerialId==line.SerialId)
            .SumAsync(x=>(decimal?)x.BaseQuantity,ct)??0m;
        return incoming-outgoing;
    }

    private async Task<decimal> PositionBalance(Guid companyId,long productId,long? variantId,long locationId,
        InventoryDispositionCode disposition,long? lotId,long? serialId,CancellationToken ct)
    {
        var d=await dbContext.Set<InventoryDispositionRecord>().AsNoTracking().SingleAsync(x=>x.Code==disposition,ct);
        var incoming=await dbContext.Set<InventoryMovementRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&x.ProductId==productId&&x.VariantId==variantId&&x.TargetLocationId==locationId&&
                x.TargetDispositionId==d.Id&&x.LotId==lotId&&x.SerialId==serialId).SumAsync(x=>(decimal?)x.BaseQuantity,ct)??0m;
        var outgoing=await dbContext.Set<InventoryMovementRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&x.ProductId==productId&&x.VariantId==variantId&&x.SourceLocationId==locationId&&
                x.SourceDispositionId==d.Id&&x.LotId==lotId&&x.SerialId==serialId).SumAsync(x=>(decimal?)x.BaseQuantity,ct)??0m;
        return incoming-outgoing;
    }

    private async Task<decimal> ReservationBalance(long reservationId,Guid companyId,CancellationToken ct)
    {
        var rows=await dbContext.Set<InventoryReservationMovementRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&x.ReservationId==reservationId).ToArrayAsync(ct);
        return rows.Sum(x=>x.Kind is ReservationMovementKind.Create or ReservationMovementKind.Increase?x.BaseQuantity:-x.BaseQuantity);
    }

    private async Task<Trade?> ResolveTrade(Guid companyId,Guid productPublicId,Guid? variantPublicId,Guid uomPublicId,CancellationToken ct)
    {
        var p=await dbContext.Set<ProductRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==productPublicId&&x.Stockable,ct);
        if(p is null)return null;
        ProductVariantRecord? v=null;
        if(variantPublicId.HasValue){
            v=await dbContext.Set<ProductVariantRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==variantPublicId&&x.ProductId==p.Id,ct);
            if(v is null)return null;
        }
        var u=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==uomPublicId,ct);
        if(u is null)return null;
        var pu=await dbContext.Set<ProductUomRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&x.ProductId==p.Id&&x.UomId==u.Id&&(x.VariantId==null||x.VariantId==v!.Id))
            .OrderByDescending(x=>x.Id).FirstOrDefaultAsync(ct);
        return pu is null?null:new Trade(p.Id,v?.Id,u.Id,pu.ConversionFactor,p.PublicId,v?.PublicId,u.PublicId);
    }

    private async Task<(long WarehouseId,long? LocationId,long? LotId,long? SerialId)?> ResolvePosition(
        Guid companyId,InventoryPosition p,Trade trade,CancellationToken ct)
    {
        var wh=await dbContext.Set<WarehouseRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==p.WarehousePublicId,ct);
        if(wh is null)return null;
        long? loc=null,lot=null,serial=null;
        if(p.LocationPublicId.HasValue){
            var l=await dbContext.Set<LocationRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==p.LocationPublicId&&x.WarehouseId==wh.Id,ct);
            if(l is null)return null;loc=l.Id;
        }
        if(p.LotPublicId.HasValue){var l=await ResolveLot(companyId,trade.ProductId,trade.VariantId,p.LotPublicId,ct);if(l is null)return null;lot=l.Id;}
        if(p.SerialPublicId.HasValue){var s=await ResolveSerial(companyId,trade.ProductId,trade.VariantId,p.SerialPublicId,ct);if(s is null)return null;serial=s.Id;}
        return (wh.Id,loc,lot,serial);
    }

    private Task<WarehouseRecord?> ActiveWarehouse(Guid companyId,Guid publicId,CancellationToken ct)=>
        dbContext.Set<WarehouseRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==publicId&&x.State==InventoryMasterState.Active,ct);
    private Task<LocationRecord?> ActiveLocation(Guid companyId,long warehouseId,Guid publicId,CancellationToken ct)=>
        dbContext.Set<LocationRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.WarehouseId==warehouseId&&x.PublicId==publicId&&x.State==InventoryMasterState.Active&&x.StockBearing,ct);
    private Task<InventoryLotRecord?> ResolveLot(Guid companyId,long productId,long? variantId,Guid? publicId,CancellationToken ct)=>
        publicId.HasValue?dbContext.Set<InventoryLotRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.ProductId==productId&&x.VariantId==variantId&&x.PublicId==publicId.Value,ct):Task.FromResult<InventoryLotRecord?>(null);
    private Task<InventorySerialRecord?> ResolveSerial(Guid companyId,long productId,long? variantId,Guid? publicId,CancellationToken ct)=>
        publicId.HasValue?dbContext.Set<InventorySerialRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.ProductId==productId&&x.VariantId==variantId&&x.PublicId==publicId.Value,ct):Task.FromResult<InventorySerialRecord?>(null);

    private async Task<(Guid Product,Guid? Variant,Guid Uom)> PublicTrade(Guid companyId,WarehouseTransferLineRecord l,CancellationToken ct)
    {
        var p=await dbContext.Set<ProductRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==companyId&&x.Id==l.ProductId,ct);
        var v=l.VariantId.HasValue?await dbContext.Set<ProductVariantRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==companyId&&x.Id==l.VariantId,ct):null;
        var u=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking().SingleAsync(x=>x.CompanyId==companyId&&x.Id==l.UomId,ct);
        return(p.PublicId,v?.PublicId,u.PublicId);
    }

    private Task<WarehouseTransferRecord?> LockTransfer(Guid companyId,Guid publicId,CancellationToken ct)=>
        dbContext.Set<WarehouseTransferRecord>().FromSqlInterpolated(
            $"SELECT * FROM warehouse.transfers WHERE company_id={companyId} AND public_id={publicId} FOR UPDATE").SingleOrDefaultAsync(ct);
    private Task<StockCountSessionRecord?> LockCount(Guid companyId,Guid publicId,CancellationToken ct)=>
        dbContext.Set<StockCountSessionRecord>().FromSqlInterpolated(
            $"SELECT * FROM warehouse.stock_count_sessions WHERE company_id={companyId} AND public_id={publicId} FOR UPDATE").SingleOrDefaultAsync(ct);

    private async Task<Dictionary<long,T>> Map<T>(Guid companyId,IEnumerable<long> ids,Func<T,long> key,CancellationToken ct) where T:class
    {
        var set=ids.Distinct().ToArray();if(set.Length==0)return [];
        var p=typeof(T).GetProperty("CompanyId")!;
        return (await dbContext.Set<T>().AsNoTracking().Where(x=>EF.Property<Guid>(x,"CompanyId")==companyId&&set.Contains(EF.Property<long>(x,"Id"))).ToArrayAsync(ct)).ToDictionary(key);
    }

    private async Task<Result<WarehouseMutationReceipt>> Mutate(
        string scope,string key,string action,string entityType,IExecutionContext context,
        Func<CancellationToken,Task<Result<WarehouseMutationReceipt>>> mutation,CancellationToken ct)
    {
        if(string.IsNullOrWhiteSpace(key))return Invalid("warehouse.operation_key.required","Idempotency operation key is required.");
        var owns=dbContext.Database.CurrentTransaction is null;IDbContextTransaction? tx=null;
        if(owns)tx=await dbContext.Database.BeginTransactionAsync(ct);
        try{
            var result=await mutation(ct);
            if(result.IsFailure){if(tx is not null)await tx.RollbackAsync(ct);dbContext.ChangeTracker.Clear();return result;}
            var now=DateTimeOffset.UtcNow;
            idempotencyStore.Add(new IdempotencyOperation(scope,key,null,now));
            auditWriter.Append(new AuditEntry(context.ActorId,context.CompanyId,context.BranchId,context.CorrelationId.Value,
                "Warehouse",action,entityType,result.Value!.PublicId,null,now));
            outboxWriter.Enqueue(new OutboxMessage(Guid.NewGuid(),"Warehouse."+action,"Warehouse",result.Value.PublicId,1,
                JsonSerializer.Serialize(new{entityType,entityPublicId=result.Value.PublicId,state=result.Value.State,version=result.Value.Version}),now,now));
            await dbContext.SaveChangesAsync(ct);
            if(!await idempotencyStore.MarkSucceededAsync(scope,key,scope+".completed",now,ct))
                throw new InvalidOperationException("Warehouse idempotency state could not be completed.");
            if(tx is not null)await tx.CommitAsync(ct);
            return result;
        }catch(DbUpdateConcurrencyException){
            if(tx is not null)await tx.RollbackAsync(ct);dbContext.ChangeTracker.Clear();
            return ConflictReceipt("warehouse.concurrency.stale","Warehouse work changed concurrently.");
        }catch(DbUpdateException ex) when(ex.InnerException is PostgresException pg&&pg.SqlState==PostgresErrorCodes.UniqueViolation){
            if(tx is not null)await tx.RollbackAsync(ct);dbContext.ChangeTracker.Clear();
            return ConflictReceipt("warehouse.unique.conflict","Warehouse operation conflicts with an existing deterministic record.");
        }finally{if(tx is not null)await tx.DisposeAsync();}
    }

    private static Result<WarehouseMutationReceipt> Success(Guid id,string state,long version,IExecutionContext c)=>
        Result<WarehouseMutationReceipt>.Success(new(id,state,version,c.CorrelationId.Value));
    private static Result<WarehouseMutationReceipt> Invalid(string code,string message)=>
        Result<WarehouseMutationReceipt>.Failure(new ApplicationError(ErrorCategory.BusinessRule,code,message));
    private static Result<T> Fail<T>(string code,string message)=>
        Result<T>.Failure(new ApplicationError(ErrorCategory.BusinessRule,code,message));
    private static Result<T> Conflict<T>(string code,string message)=>
        Result<T>.Failure(new ApplicationError(ErrorCategory.Concurrency,code,message));
    private static Result<WarehouseMutationReceipt> ConflictReceipt(string code,string message)=>
        Result<WarehouseMutationReceipt>.Failure(new ApplicationError(ErrorCategory.Concurrency,code,message));
    private static string? Normalize(string? value)=>string.IsNullOrWhiteSpace(value)?null:value.Trim();
}

public sealed class EfWarehouseTransactionCoordinator(MarsDbContext dbContext):IWarehouseTransactionCoordinator
{
    public async Task<Result<T>> ExecuteAsync<T>(Func<CancellationToken,Task<Result<T>>> operation,CancellationToken ct)
    {
        if(dbContext.Database.CurrentTransaction is not null)return await operation(ct);
        await using var tx=await dbContext.Database.BeginTransactionAsync(ct);
        try{
            var result=await operation(ct);
            if(result.IsFailure){await tx.RollbackAsync(ct);dbContext.ChangeTracker.Clear();return result;}
            await tx.CommitAsync(ct);return result;
        }catch{await tx.RollbackAsync(ct);dbContext.ChangeTracker.Clear();throw;}
    }
}
