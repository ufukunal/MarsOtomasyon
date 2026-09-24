using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Sales;
using Mars.Domain.Inventory;
using Mars.Domain.Parties;
using Mars.Domain.Sales;
using Mars.Infrastructure.Persistence.Inventory;
using Mars.Infrastructure.Persistence.Parties;
using Mars.Infrastructure.Persistence.Products;
using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence.Sales;

public sealed partial class EfSalesPersistence
{
    public Task<Result<SalesMutationReceipt>> CreateDispatchAsync(
        CreateDispatchCommand command,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.dispatch.create",command.OperationKey,"DispatchCreated","Dispatch",
            "sales.dispatch.create.completed",context,
            async innerCt =>
            {
                if(string.IsNullOrWhiteSpace(command.Number)||command.Lines.Count==0||
                   command.Lines.Any(x=>x.Sequence<=0||x.Quantity<=0m)||
                   command.Lines.GroupBy(x=>x.Sequence).Any(g=>g.Count()!=1))
                    return Validation<SalesMutationReceipt>("sales.dispatch.invalid","Dispatch number and valid unique positive lines are required.");

                var order=await LockOrderAsync(command.SalesOrderPublicId,context.CompanyId,innerCt);
                if(order is null)return NotFound<SalesMutationReceipt>("sales.order.not_found","Sales Order was not found.");
                if(order.State is not (SalesOrderState.Confirmed or SalesOrderState.PartiallyCompleted))
                    return Business<SalesMutationReceipt>("sales.order.state","Dispatch requires a confirmed effective Sales Order.");
                if(order.CurrentVersionNumber!=command.SalesOrderVersion)
                    return Conflict<SalesMutationReceipt>("sales.order.version","Dispatch requires the exact effective Sales Order version.",true);

                var warehouse=await dbContext.Set<WarehouseRecord>().AsNoTracking()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==command.WarehousePublicId&&x.State==InventoryMasterState.Active,innerCt);
                if(warehouse is null)return Business<SalesMutationReceipt>("sales.warehouse.not_active","Warehouse must be ACTIVE.");
                var ov=await CurrentOrderVersionAsync(order,innerCt);
                var orderLines=await dbContext.Set<SalesOrderLineRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==context.CompanyId&&x.SalesOrderVersionId==ov.Id)
                    .ToDictionaryAsync(x=>x.LinePublicId,innerCt);
                if(command.Lines.Select(x=>x.SalesOrderLinePublicId).Distinct().Count()!=command.Lines.Count ||
                   command.Lines.Any(x=>!orderLines.ContainsKey(x.SalesOrderLinePublicId)))
                    return Validation<SalesMutationReceipt>("sales.dispatch.lines","Dispatch lines must target unique lines in the effective Order version.");

                var now=DateTimeOffset.UtcNow;
                var dispatch=new DispatchRecord {
                    PublicId=Guid.NewGuid(),CompanyId=context.CompanyId,Number=command.Number.Trim(),SalesOrderId=order.Id,
                    SalesOrderVersionNumber=order.CurrentVersionNumber,WarehouseId=warehouse.Id,State=DispatchState.Draft,
                    Version=1,CreatorActorId=context.ActorId,CreatedAt=now
                };
                dbContext.Add(dispatch);await dbContext.SaveChangesAsync(innerCt);

                foreach(var input in command.Lines.OrderBy(x=>x.Sequence))
                {
                    var src=orderLines[input.SalesOrderLinePublicId];
                    var location=await ResolveLocationAsync(context.CompanyId,warehouse.Id,input.LocationPublicId,innerCt);
                    if(input.LocationPublicId.HasValue&&location is null)
                        return Business<SalesMutationReceipt>("sales.dispatch.location","Location must belong to the selected Warehouse.");
                    var lot=await ResolveLotAsync(context.CompanyId,src,input.LotPublicId,innerCt);
                    if(input.LotPublicId.HasValue&&lot is null)
                        return Business<SalesMutationReceipt>("sales.dispatch.lot","Lot must match the Order line Product/Variant.");
                    var serial=await ResolveSerialAsync(context.CompanyId,src,input.SerialPublicId,lot?.Id,innerCt);
                    if(input.SerialPublicId.HasValue&&serial is null)
                        return Business<SalesMutationReceipt>("sales.dispatch.serial","Serial must match the Order line Product/Variant/Lot.");

                    if(input.ReservationPublicId.HasValue)
                    {
                        var reservation=await dbContext.Set<InventoryReservationRecord>().AsNoTracking()
                            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==input.ReservationPublicId.Value,innerCt);
                        if(reservation is null||reservation.SalesOrderPublicId!=order.PublicId||
                           reservation.SalesOrderLinePublicId!=src.LinePublicId||reservation.WarehouseId!=warehouse.Id)
                            return Business<SalesMutationReceipt>("sales.dispatch.reservation","Reservation must match exact Order line and Warehouse.");
                        if(await ReservationBalanceAsync(reservation.Id,context.CompanyId,innerCt)<input.Quantity)
                            return Business<SalesMutationReceipt>("sales.dispatch.reservation.quantity","Linked Reservation balance is insufficient.");
                    }

                    dbContext.Add(new DispatchLineRecord {
                        PublicId=Guid.NewGuid(),DispatchId=dispatch.Id,CompanyId=context.CompanyId,Sequence=input.Sequence,
                        SalesOrderLinePublicId=src.LinePublicId,ProductId=src.ProductId,VariantId=src.VariantId,UomId=src.UomId,
                        ConversionFactorSnapshot=src.ConversionFactorSnapshot,Quantity=input.Quantity,WarehouseId=warehouse.Id,
                        LocationId=location?.Id,LotId=lot?.Id,SerialId=serial?.Id,ReservationPublicId=input.ReservationPublicId
                    });
                }
                return Result<SalesMutationReceipt>.Success(new(dispatch.PublicId,"DRAFT",dispatch.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<SalesMutationReceipt>> ReadyDispatchAsync(
        Guid id,long version,string key,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.dispatch.ready",key,"DispatchReadied","Dispatch","sales.dispatch.ready.completed",context,
            async innerCt =>
            {
                var dispatch=await LockDispatchAsync(id,context.CompanyId,innerCt);
                if(dispatch is null)return NotFound<SalesMutationReceipt>("sales.dispatch.not_found","Dispatch was not found.");
                if(dispatch.Version!=version)return Conflict<SalesMutationReceipt>("sales.dispatch.stale","Dispatch version is stale.",true);
                if(dispatch.State!=DispatchState.Draft)return Business<SalesMutationReceipt>("sales.dispatch.state","Only DRAFT Dispatch can become READY.");
                dispatch.State=DispatchState.Ready;dispatch.ReadyAt=DateTimeOffset.UtcNow;dispatch.Version++;
                return Result<SalesMutationReceipt>.Success(new(dispatch.PublicId,"READY",dispatch.Version,context.CorrelationId.Value));
            },ct);

    public async Task<Result<SalesDispatchPostPlan>> PrepareDispatchPostAsync(
        Guid id,IExecutionContext context,CancellationToken ct)
    {
        var dispatch=await LockDispatchAsync(id,context.CompanyId,ct);
        if(dispatch is null)return NotFound<SalesDispatchPostPlan>("sales.dispatch.not_found","Dispatch was not found.");
        if(dispatch.State!=DispatchState.Ready)return Business<SalesDispatchPostPlan>("sales.dispatch.state","Only READY Dispatch can be posted.");
        var order=await LockOrderByIdAsync(dispatch.SalesOrderId,context.CompanyId,ct);
        if(order is null)return NotFound<SalesDispatchPostPlan>("sales.order.not_found","Sales Order was not found.");
        if(order.CurrentVersionNumber!=dispatch.SalesOrderVersionNumber)
            return Conflict<SalesDispatchPostPlan>("sales.dispatch.source_version","Dispatch source Order version is no longer effective.",true);
        if(order.State is not (SalesOrderState.Confirmed or SalesOrderState.PartiallyCompleted))
            return Business<SalesDispatchPostPlan>("sales.order.state","Sales Order is not eligible for Dispatch posting.");

        var warehouse=await dbContext.Set<WarehouseRecord>().AsNoTracking()
            .SingleAsync(x=>x.Id==dispatch.WarehouseId&&x.CompanyId==context.CompanyId,ct);
        var ov=await CurrentOrderVersionAsync(order,ct);
        var sourceLines=await dbContext.Set<SalesOrderLineRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&x.SalesOrderVersionId==ov.Id).ToDictionaryAsync(x=>x.LinePublicId,ct);
        var lines=await dbContext.Set<DispatchLineRecord>()
            .Where(x=>x.CompanyId==context.CompanyId&&x.DispatchId==dispatch.Id).OrderBy(x=>x.Sequence).ToArrayAsync(ct);
        var shipped=await GetNetDispatchedByOrderLineAsync(context.CompanyId,order.Id,lines.Select(x=>x.SalesOrderLinePublicId).Distinct().ToArray(),ct);

        foreach(var group in lines.GroupBy(x=>x.SalesOrderLinePublicId))
        {
            if(!sourceLines.TryGetValue(group.Key,out var source))
                return Business<SalesDispatchPostPlan>("sales.dispatch.source_line","Dispatch source line is absent from effective Order.");
            if(shipped.GetValueOrDefault(group.Key)+group.Sum(x=>x.Quantity)>source.Quantity)
                return Business<SalesDispatchPostPlan>("sales.dispatch.cap","Cumulative Dispatch quantity exceeds eligible Order remainder.");
        }

        var plans=new List<SalesDispatchPostLinePlan>(lines.Length);
        foreach(var line in lines)
        {
            var product=await dbContext.Set<ProductRecord>().AsNoTracking().SingleAsync(x=>x.Id==line.ProductId&&x.CompanyId==context.CompanyId,ct);
            ProductVariantRecord? variant=line.VariantId.HasValue
                ? await dbContext.Set<ProductVariantRecord>().AsNoTracking().SingleAsync(x=>x.Id==line.VariantId.Value&&x.CompanyId==context.CompanyId,ct):null;
            var uom=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking().SingleAsync(x=>x.Id==line.UomId&&x.CompanyId==context.CompanyId,ct);
            var location=line.LocationId.HasValue?await dbContext.Set<LocationRecord>().AsNoTracking().SingleAsync(x=>x.Id==line.LocationId.Value&&x.CompanyId==context.CompanyId,ct):null;
            var lot=line.LotId.HasValue?await dbContext.Set<InventoryLotRecord>().AsNoTracking().SingleAsync(x=>x.Id==line.LotId.Value&&x.CompanyId==context.CompanyId,ct):null;
            var serial=line.SerialId.HasValue?await dbContext.Set<InventorySerialRecord>().AsNoTracking().SingleAsync(x=>x.Id==line.SerialId.Value&&x.CompanyId==context.CompanyId,ct):null;
            if(line.ReservationPublicId.HasValue)
            {
                var reservation=await dbContext.Set<InventoryReservationRecord>().AsNoTracking()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==line.ReservationPublicId.Value,ct);
                if(reservation is null||reservation.SalesOrderPublicId!=order.PublicId||
                   reservation.SalesOrderLinePublicId!=line.SalesOrderLinePublicId||reservation.WarehouseId!=warehouse.Id||
                   await ReservationBalanceAsync(reservation.Id,context.CompanyId,ct)<line.Quantity)
                    return Business<SalesDispatchPostPlan>("sales.dispatch.reservation","Linked Reservation is not consumable for this Dispatch line.");
            }
            plans.Add(new(line.PublicId,line.SalesOrderLinePublicId,product.PublicId,variant?.PublicId,uom.PublicId,
                line.ConversionFactorSnapshot,line.Quantity,
                InventoryPosition.Create(warehouse.PublicId,location?.PublicId,InventoryDispositionCode.Available,lot?.PublicId,serial?.PublicId),
                line.ReservationPublicId));
        }
        return Result<SalesDispatchPostPlan>.Success(new(dispatch.PublicId,order.PublicId,warehouse.PublicId,plans));
    }

    public Task<Result<SalesMutationReceipt>> CompleteDispatchPostAsync(
        Guid id,IReadOnlyList<SalesDispatchEffect> effects,string key,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.dispatch.post",key,"DispatchPosted","Dispatch","sales.dispatch.post.completed",context,
            async innerCt =>
            {
                var dispatch=await LockDispatchAsync(id,context.CompanyId,innerCt);
                if(dispatch is null)return NotFound<SalesMutationReceipt>("sales.dispatch.not_found","Dispatch was not found.");
                if(dispatch.State!=DispatchState.Ready)return Business<SalesMutationReceipt>("sales.dispatch.state","Only READY Dispatch can complete posting.");
                var lines=await dbContext.Set<DispatchLineRecord>()
                    .Where(x=>x.CompanyId==context.CompanyId&&x.DispatchId==dispatch.Id).ToArrayAsync(innerCt);
                if(effects.Count!=lines.Length||effects.Any(e=>lines.All(l=>l.PublicId!=e.DispatchLinePublicId))||
                   effects.Any(e=>e.IsReversal||e.OriginalInventoryMovementPublicId.HasValue))
                    return Conflict<SalesMutationReceipt>("sales.dispatch.effects","Inventory effects do not exactly match Dispatch lines.");
                foreach(var e in effects)
                {
                    var line=lines.Single(x=>x.PublicId==e.DispatchLinePublicId);
                    dbContext.Add(new DispatchInventoryEffectLinkRecord {
                        PublicId=Guid.NewGuid(),DispatchLineId=line.Id,CompanyId=context.CompanyId,
                        InventoryMovementPublicId=e.InventoryMovementPublicId,OriginalInventoryMovementPublicId=null,
                        IsReversal=false,CreatedAt=DateTimeOffset.UtcNow
                    });
                }
                dispatch.State=DispatchState.Posted;dispatch.PostedAt=DateTimeOffset.UtcNow;dispatch.Version++;

                var order=await LockOrderByIdAsync(dispatch.SalesOrderId,context.CompanyId,innerCt);
                if(order is not null)
                {
                    var ov=await CurrentOrderVersionAsync(order,innerCt);
                    var source=await dbContext.Set<SalesOrderLineRecord>().AsNoTracking()
                        .Where(x=>x.CompanyId==context.CompanyId&&x.SalesOrderVersionId==ov.Id).ToArrayAsync(innerCt);
                    var prior=await GetNetDispatchedByOrderLineAsync(context.CompanyId,order.Id,source.Select(x=>x.LinePublicId).ToArray(),innerCt);
                    foreach(var line in lines)prior[line.SalesOrderLinePublicId]=prior.GetValueOrDefault(line.SalesOrderLinePublicId)+line.Quantity;
                    order.State=source.All(x=>prior.GetValueOrDefault(x.LinePublicId)>=x.Quantity)
                        ?SalesOrderState.Completed:SalesOrderState.PartiallyCompleted;
                    if(order.State==SalesOrderState.Completed)order.ClosedAt=DateTimeOffset.UtcNow;
                    order.Version++;
                }
                return Result<SalesMutationReceipt>.Success(new(dispatch.PublicId,"POSTED",dispatch.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<SalesMutationReceipt>> HandoffDispatchAsync(Guid id,long version,string key,IExecutionContext context,CancellationToken ct) =>
        ChangeDispatchState(id,version,key,context,DispatchState.Posted,DispatchState.HandedOver,
            "sales.dispatch.handoff","DispatchHandedOver","HANDED_OVER",ct);

    public Task<Result<SalesMutationReceipt>> DeliverDispatchAsync(Guid id,long version,string key,IExecutionContext context,CancellationToken ct) =>
        ChangeDispatchState(id,version,key,context,DispatchState.HandedOver,DispatchState.Delivered,
            "sales.dispatch.deliver","DispatchDelivered","DELIVERED",ct);

    public Task<Result<SalesMutationReceipt>> CancelDispatchAsync(
        Guid id,long version,string reason,string key,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.dispatch.cancel",key,"DispatchCancelled","Dispatch","sales.dispatch.cancel.completed",context,
            async innerCt =>
            {
                if(string.IsNullOrWhiteSpace(reason))return Validation<SalesMutationReceipt>("sales.reason.required","Cancellation reason is required.");
                var d=await LockDispatchAsync(id,context.CompanyId,innerCt);
                if(d is null)return NotFound<SalesMutationReceipt>("sales.dispatch.not_found","Dispatch was not found.");
                if(d.Version!=version)return Conflict<SalesMutationReceipt>("sales.dispatch.stale","Dispatch version is stale.",true);
                if(d.State is not (DispatchState.Draft or DispatchState.Ready))
                    return Business<SalesMutationReceipt>("sales.dispatch.state","Only DRAFT or READY Dispatch can be cancelled without reversal.");
                d.State=DispatchState.Cancelled;d.CancelledAt=DateTimeOffset.UtcNow;d.Version++;
                return Result<SalesMutationReceipt>.Success(new(d.PublicId,"CANCELLED",d.Version,context.CorrelationId.Value));
            },ct);

    public async Task<Result<SalesDispatchReversePlan>> PrepareDispatchReverseAsync(
        ReverseDispatchCommand command,IExecutionContext context,CancellationToken ct)
    {
        if(string.IsNullOrWhiteSpace(command.ReversalNumber)||string.IsNullOrWhiteSpace(command.Reason))
            return Validation<SalesDispatchReversePlan>("sales.dispatch.reverse.invalid","Reversal number and reason are required.");
        var original=await LockDispatchAsync(command.DispatchPublicId,context.CompanyId,ct);
        if(original is null)return NotFound<SalesDispatchReversePlan>("sales.dispatch.not_found","Dispatch was not found.");
        if(original.State is not (DispatchState.Posted or DispatchState.HandedOver or DispatchState.Delivered))
            return Business<SalesDispatchReversePlan>("sales.dispatch.reverse.state","Only posted physical Dispatch can be reversed.");
        if(await dbContext.Set<DispatchRecord>().AsNoTracking().AnyAsync(x=>x.CompanyId==context.CompanyId&&x.ReversalOfDispatchId==original.Id,ct))
            return Conflict<SalesDispatchReversePlan>("sales.dispatch.reverse.exists","Dispatch already has an explicit reversal.");

        var order=await LockOrderByIdAsync(original.SalesOrderId,context.CompanyId,ct);
        if(order is null)return NotFound<SalesDispatchReversePlan>("sales.order.not_found","Sales Order was not found.");
        var warehouse=await dbContext.Set<WarehouseRecord>().AsNoTracking().SingleAsync(x=>x.Id==original.WarehouseId&&x.CompanyId==context.CompanyId,ct);
        var originalLines=await dbContext.Set<DispatchLineRecord>()
            .Where(x=>x.CompanyId==context.CompanyId&&x.DispatchId==original.Id).OrderBy(x=>x.Sequence).ToArrayAsync(ct);
        var effects=await dbContext.Set<DispatchInventoryEffectLinkRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&originalLines.Select(l=>l.Id).Contains(x.DispatchLineId)&&!x.IsReversal)
            .ToDictionaryAsync(x=>x.DispatchLineId,ct);
        if(effects.Count!=originalLines.Length)
            return Conflict<SalesDispatchReversePlan>("sales.dispatch.reverse.effects","Original Dispatch inventory effect links are incomplete.");

        var reversal=new DispatchRecord {
            PublicId=Guid.NewGuid(),CompanyId=context.CompanyId,Number=command.ReversalNumber.Trim(),SalesOrderId=original.SalesOrderId,
            SalesOrderVersionNumber=original.SalesOrderVersionNumber,WarehouseId=original.WarehouseId,State=DispatchState.Ready,
            Version=1,CreatorActorId=context.ActorId,ReversalOfDispatchId=original.Id,CreatedAt=DateTimeOffset.UtcNow,ReadyAt=DateTimeOffset.UtcNow
        };
        dbContext.Add(reversal);await dbContext.SaveChangesAsync(ct);

        var plans=new List<SalesDispatchReverseLinePlan>(originalLines.Length);
        foreach(var line in originalLines)
        {
            var reversalLine=new DispatchLineRecord {
                PublicId=Guid.NewGuid(),DispatchId=reversal.Id,CompanyId=context.CompanyId,Sequence=line.Sequence,
                SalesOrderLinePublicId=line.SalesOrderLinePublicId,ProductId=line.ProductId,VariantId=line.VariantId,UomId=line.UomId,
                ConversionFactorSnapshot=line.ConversionFactorSnapshot,Quantity=line.Quantity,WarehouseId=line.WarehouseId,
                LocationId=line.LocationId,LotId=line.LotId,SerialId=line.SerialId,ReservationPublicId=null
            };
            dbContext.Add(reversalLine);await dbContext.SaveChangesAsync(ct);
            var product=await dbContext.Set<ProductRecord>().AsNoTracking().SingleAsync(x=>x.Id==line.ProductId&&x.CompanyId==context.CompanyId,ct);
            ProductVariantRecord? variant=line.VariantId.HasValue?await dbContext.Set<ProductVariantRecord>().AsNoTracking().SingleAsync(x=>x.Id==line.VariantId.Value&&x.CompanyId==context.CompanyId,ct):null;
            var uom=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking().SingleAsync(x=>x.Id==line.UomId&&x.CompanyId==context.CompanyId,ct);
            var location=line.LocationId.HasValue?await dbContext.Set<LocationRecord>().AsNoTracking().SingleAsync(x=>x.Id==line.LocationId.Value&&x.CompanyId==context.CompanyId,ct):null;
            var lot=line.LotId.HasValue?await dbContext.Set<InventoryLotRecord>().AsNoTracking().SingleAsync(x=>x.Id==line.LotId.Value&&x.CompanyId==context.CompanyId,ct):null;
            var serial=line.SerialId.HasValue?await dbContext.Set<InventorySerialRecord>().AsNoTracking().SingleAsync(x=>x.Id==line.SerialId.Value&&x.CompanyId==context.CompanyId,ct):null;
            plans.Add(new(reversalLine.PublicId,line.PublicId,line.SalesOrderLinePublicId,product.PublicId,variant?.PublicId,uom.PublicId,
                line.ConversionFactorSnapshot,line.Quantity,
                InventoryPosition.Create(warehouse.PublicId,location?.PublicId,InventoryDispositionCode.Available,lot?.PublicId,serial?.PublicId),
                effects[line.Id].InventoryMovementPublicId));
        }
        return Result<SalesDispatchReversePlan>.Success(new(original.PublicId,reversal.PublicId,order.PublicId,warehouse.PublicId,plans));
    }

    public Task<Result<SalesMutationReceipt>> CompleteDispatchReverseAsync(
        Guid reversalId,IReadOnlyList<SalesDispatchEffect> effects,string key,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.dispatch.reverse",key,"DispatchReversed","Dispatch","sales.dispatch.reverse.completed",context,
            async innerCt =>
            {
                var reversal=await LockDispatchAsync(reversalId,context.CompanyId,innerCt);
                if(reversal is null||!reversal.ReversalOfDispatchId.HasValue)
                    return NotFound<SalesMutationReceipt>("sales.dispatch.reversal.not_found","Reversal Dispatch was not found.");
                if(reversal.State!=DispatchState.Ready)return Business<SalesMutationReceipt>("sales.dispatch.reverse.state","Reversal Dispatch is not pending completion.");
                var original=await LockDispatchByIdAsync(reversal.ReversalOfDispatchId.Value,context.CompanyId,innerCt);
                if(original is null)return NotFound<SalesMutationReceipt>("sales.dispatch.not_found","Original Dispatch was not found.");
                var lines=await dbContext.Set<DispatchLineRecord>().Where(x=>x.CompanyId==context.CompanyId&&x.DispatchId==reversal.Id).ToArrayAsync(innerCt);
                if(effects.Count!=lines.Length||effects.Any(e=>!e.IsReversal||!e.OriginalInventoryMovementPublicId.HasValue||lines.All(l=>l.PublicId!=e.DispatchLinePublicId)))
                    return Conflict<SalesMutationReceipt>("sales.dispatch.reverse.effects","Reversal inventory effects do not exactly match reversal lines.");
                foreach(var e in effects)
                {
                    var line=lines.Single(x=>x.PublicId==e.DispatchLinePublicId);
                    dbContext.Add(new DispatchInventoryEffectLinkRecord {
                        PublicId=Guid.NewGuid(),DispatchLineId=line.Id,CompanyId=context.CompanyId,
                        InventoryMovementPublicId=e.InventoryMovementPublicId,OriginalInventoryMovementPublicId=e.OriginalInventoryMovementPublicId,
                        IsReversal=true,CreatedAt=DateTimeOffset.UtcNow
                    });
                }
                var now=DateTimeOffset.UtcNow;
                reversal.State=DispatchState.Reversed;reversal.PostedAt=now;reversal.Version++;

                var order=await LockOrderByIdAsync(original.SalesOrderId,context.CompanyId,innerCt);
                if(order is not null&&order.State==SalesOrderState.Completed)
                {
                    order.State=SalesOrderState.PartiallyCompleted;order.ClosedAt=null;order.Version++;
                }
                return Result<SalesMutationReceipt>.Success(new(reversal.PublicId,"REVERSED",reversal.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<SalesMutationReceipt>> CreateInvoiceDraftAsync(
        CreateInvoiceDraftCommand command,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.invoice.create",command.OperationKey,"InvoiceDraftCreated","SalesInvoice",
            "sales.invoice.create.completed",context,
            async innerCt => await BuildInvoiceAsync(null,0,command,context,innerCt),ct);

    public Task<Result<SalesMutationReceipt>> ReplaceInvoiceDraftAsync(
        Guid id,long version,CreateInvoiceDraftCommand command,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.invoice.edit_draft",command.OperationKey,"InvoiceDraftReplaced","SalesInvoice",
            "sales.invoice.replace.completed",context,
            async innerCt => await BuildInvoiceAsync(id,version,command,context,innerCt),ct);

    public Task<Result<SalesMutationReceipt>> CancelInvoiceDraftAsync(
        Guid id,long version,string reason,string key,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("sales.invoice.cancel",key,"InvoiceDraftCancelled","SalesInvoice","sales.invoice.cancel.completed",context,
            async innerCt =>
            {
                if(string.IsNullOrWhiteSpace(reason))return Validation<SalesMutationReceipt>("sales.reason.required","Cancellation reason is required.");
                var invoice=await dbContext.Set<SalesInvoiceRecord>().SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==id,innerCt);
                if(invoice is null)return NotFound<SalesMutationReceipt>("sales.invoice.not_found","Invoice draft was not found.");
                if(invoice.Version!=version)return Conflict<SalesMutationReceipt>("sales.invoice.stale","Invoice draft version is stale.",true);
                if(invoice.State!=SalesInvoiceState.Draft)return Business<SalesMutationReceipt>("sales.invoice.state","Only DRAFT Invoice can be cancelled.");
                invoice.State=SalesInvoiceState.Cancelled;invoice.CancelledAt=DateTimeOffset.UtcNow;invoice.Version++;
                return Result<SalesMutationReceipt>.Success(new(invoice.PublicId,"CANCELLED",invoice.Version,context.CorrelationId.Value));
            },ct);

    private async Task<Result<SalesMutationReceipt>> BuildInvoiceAsync(
        Guid? existingId,long expectedVersion,CreateInvoiceDraftCommand command,IExecutionContext context,CancellationToken ct)
    {
        if(string.IsNullOrWhiteSpace(command.Number)||command.Lines.Count==0||command.DueDate<command.DocumentDate||
           command.DocumentDiscountPercent<0m||command.DocumentDiscountPercent>100m||
           command.Lines.Any(x=>x.Sequence<=0||x.Quantity<=0m||x.UnitPrice<0m||x.LineDiscountPercent<0m||x.LineDiscountPercent>100m||x.TaxPercent<0m||x.TaxPercent>100m)||
           command.Lines.GroupBy(x=>x.Sequence).Any(g=>g.Count()!=1))
            return Validation<SalesMutationReceipt>("sales.invoice.invalid","Invoice draft header or lines are invalid.");
        var currency=command.CurrencyCode.Trim().ToUpperInvariant();
        if(currency.Length!=3||currency.Any(x=>x<'A'||x>'Z'))
            return Validation<SalesMutationReceipt>("sales.currency.invalid","Currency must be a three-letter ISO-style code.");
        var customer=await ResolveCustomerAsync(context.CompanyId,command.CustomerPartyPublicId,ct);
        if(customer is null)return Business<SalesMutationReceipt>("sales.customer.not_eligible","Customer must be ACTIVE with an ACTIVE Customer role.");

        var resolved=new List<ResolvedTrade>();
        foreach(var x in command.Lines.OrderBy(x=>x.Sequence))
        {
            var r=await ResolveTradeAsync(context.CompanyId,x.ProductPublicId,x.VariantPublicId,x.UomPublicId,ct);
            if(r.IsFailure)return Result<SalesMutationReceipt>.Failure(r.Error!);
            resolved.Add(r.Value!);
            var sourceCheck=await ValidateInvoiceSourceAsync(command.SourceMode,x,context.CompanyId,existingId,ct);
            if(sourceCheck is not null)return Result<SalesMutationReceipt>.Failure(sourceCheck);
        }

        SalesInvoiceRecord invoice;
        if(existingId.HasValue)
        {
            var existing=await dbContext.Set<SalesInvoiceRecord>().SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==existingId.Value,ct);
            if(existing is null)return NotFound<SalesMutationReceipt>("sales.invoice.not_found","Invoice draft was not found.");
            invoice=existing;
            if(invoice.Version!=expectedVersion)return Conflict<SalesMutationReceipt>("sales.invoice.stale","Invoice draft version is stale.",true);
            if(invoice.State!=SalesInvoiceState.Draft)return Business<SalesMutationReceipt>("sales.invoice.state","Only DRAFT Invoice can be edited.");
            var oldLines=await dbContext.Set<SalesInvoiceLineRecord>().Where(x=>x.CompanyId==context.CompanyId&&x.SalesInvoiceId==invoice.Id).ToArrayAsync(ct);
            var oldIds=oldLines.Select(x=>x.Id).ToArray();
            var links=await dbContext.Set<SalesInvoiceSourceLinkRecord>().Where(x=>x.CompanyId==context.CompanyId&&oldIds.Contains(x.SalesInvoiceLineId)).ToArrayAsync(ct);
            dbContext.RemoveRange(links);dbContext.RemoveRange(oldLines);
            invoice.Number=command.Number.Trim();invoice.CustomerPartyId=customer.Id;invoice.SourceMode=command.SourceMode;
            invoice.DocumentDate=command.DocumentDate;invoice.DueDate=command.DueDate;invoice.CurrencyCode=currency;
            invoice.DocumentDiscountPercent=command.DocumentDiscountPercent;invoice.CustomerCodeSnapshot=customer.Code;
            invoice.CustomerLegalNameSnapshot=customer.LegalName;invoice.Version++;
        }
        else
        {
            invoice=new SalesInvoiceRecord {
                PublicId=Guid.NewGuid(),CompanyId=context.CompanyId,Number=command.Number.Trim(),CustomerPartyId=customer.Id,
                State=SalesInvoiceState.Draft,SourceMode=command.SourceMode,DocumentDate=command.DocumentDate,DueDate=command.DueDate,
                CurrencyCode=currency,DocumentDiscountPercent=command.DocumentDiscountPercent,CustomerCodeSnapshot=customer.Code,
                CustomerLegalNameSnapshot=customer.LegalName,Version=1,CreatorActorId=context.ActorId,CreatedAt=DateTimeOffset.UtcNow
            };
            dbContext.Add(invoice);await dbContext.SaveChangesAsync(ct);
        }

        var tax=await dbContext.Set<PartyTaxIdentityRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&x.PartyId==customer.Id&&x.State==PartyTaxIdentityState.Active)
            .OrderByDescending(x=>x.Jurisdiction=="TR").ThenBy(x=>x.Id).FirstOrDefaultAsync(ct);
        invoice.CustomerTaxSchemeSnapshot=tax?.Scheme.ToString().ToUpperInvariant();
        invoice.CustomerTaxValueSnapshot=tax?.Value;
        invoice.BillingAddressSnapshot=await AddressSnapshotAsync(customer.Id,context.CompanyId,PartyAddressPurpose.Billing,ct);
        invoice.ShippingAddressSnapshot=await AddressSnapshotAsync(customer.Id,context.CompanyId,PartyAddressPurpose.Shipping,ct);

        var calc=SalesCommercialCalculator.Calculate(
            command.Lines.OrderBy(x=>x.Sequence).Select(x=>new SalesCommercialLineInput(x.Sequence,x.Quantity,x.UnitPrice,x.LineDiscountPercent,x.TaxPercent)).ToArray(),
            command.DocumentDiscountPercent,2);
        invoice.NetTotal=calc.NetTotal;invoice.TaxTotal=calc.TaxTotal;invoice.GrossTotal=calc.GrossTotal;
        await dbContext.SaveChangesAsync(ct);

        var inputs=command.Lines.OrderBy(x=>x.Sequence).ToArray();
        for(var i=0;i<inputs.Length;i++)
        {
            var x=inputs[i];var r=resolved[i];var c=calc.Lines[i];
            var line=new SalesInvoiceLineRecord {
                PublicId=Guid.NewGuid(),SalesInvoiceId=invoice.Id,CompanyId=context.CompanyId,Sequence=x.Sequence,
                ProductId=r.ProductId,VariantId=r.VariantId,UomId=r.UomId,ConversionFactorSnapshot=r.ConversionFactor,
                ProductCodeSnapshot=r.ProductCode,ProductNameSnapshot=r.ProductName,VariantCodeSnapshot=r.VariantCode,
                VariantNameSnapshot=r.VariantName,UomCodeSnapshot=r.UomCode,UomNameSnapshot=r.UomName,
                Quantity=x.Quantity,UnitPrice=x.UnitPrice,LineDiscountPercent=x.LineDiscountPercent,DocumentDiscount=c.DocumentDiscount,
                TaxPercent=x.TaxPercent,TaxableBase=c.TaxableBase,TaxAmount=c.Tax,LineTotal=c.LineTotal
            };
            dbContext.Add(line);await dbContext.SaveChangesAsync(ct);
            dbContext.Add(new SalesInvoiceSourceLinkRecord {
                PublicId=Guid.NewGuid(),SalesInvoiceLineId=line.Id,CompanyId=context.CompanyId,SourceMode=command.SourceMode,
                SourceDocumentPublicId=x.SourceDocumentPublicId,SourceLinePublicId=x.SourceLinePublicId,SourceVersion=x.SourceVersion,Quantity=x.Quantity
            });
        }
        return Result<SalesMutationReceipt>.Success(new(invoice.PublicId,"DRAFT",invoice.Version,context.CorrelationId.Value));
    }

    private async Task<ApplicationError?> ValidateInvoiceSourceAsync(
        SalesInvoiceSourceMode mode,CreateInvoiceDraftLineInput input,Guid companyId,Guid? excludeInvoicePublicId,CancellationToken ct)
    {
        if(mode==SalesInvoiceSourceMode.Direct)
        {
            if(input.SourceDocumentPublicId.HasValue||input.SourceLinePublicId.HasValue||input.SourceVersion.HasValue)
                return new(ErrorCategory.Validation,"sales.invoice.source.direct","Direct Invoice line cannot carry Order/Dispatch source identity.");
            return null;
        }
        if(!input.SourceDocumentPublicId.HasValue||!input.SourceLinePublicId.HasValue)
            return new(ErrorCategory.Validation,"sales.invoice.source.required","Sourced Invoice line requires exact source document and line.");

        decimal sourceQty;
        if(mode==SalesInvoiceSourceMode.Dispatch)
        {
            var dispatch=await dbContext.Set<DispatchRecord>()
                .FromSqlInterpolated(
                    $"SELECT * FROM sales.dispatches WHERE company_id = {companyId} AND public_id = {input.SourceDocumentPublicId.Value} FOR UPDATE")
                .SingleOrDefaultAsync(ct);
            if(dispatch is null || dispatch.State is not (DispatchState.Posted or DispatchState.HandedOver or DispatchState.Delivered))
                return new(ErrorCategory.BusinessRule,"sales.invoice.dispatch_source","Dispatch source must be physically posted and unreversed.");
            var line=await dbContext.Set<DispatchLineRecord>().AsNoTracking()
                .SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.DispatchId==dispatch.Id&&x.PublicId==input.SourceLinePublicId.Value,ct);
            if(line is null)return new(ErrorCategory.NotFound,"sales.invoice.source_line","Dispatch source line was not found.");
            sourceQty=line.Quantity;
        }
        else
        {
            var order=await dbContext.Set<SalesOrderRecord>()
                .FromSqlInterpolated(
                    $"SELECT * FROM sales.sales_orders WHERE company_id = {companyId} AND public_id = {input.SourceDocumentPublicId.Value} FOR UPDATE")
                .SingleOrDefaultAsync(ct);
            if(order is null)return new(ErrorCategory.NotFound,"sales.invoice.order_source","Order source was not found.");
            if(input.SourceVersion.HasValue&&input.SourceVersion.Value!=order.CurrentVersionNumber)
                return new(ErrorCategory.Concurrency,"sales.invoice.order_version","Invoice requires exact effective Order version.");
            var ov=await dbContext.Set<SalesOrderVersionRecord>().AsNoTracking()
                .SingleAsync(x=>x.CompanyId==companyId&&x.SalesOrderId==order.Id&&x.VersionNumber==order.CurrentVersionNumber,ct);
            var line=await dbContext.Set<SalesOrderLineRecord>().AsNoTracking()
                .SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.SalesOrderVersionId==ov.Id&&x.LinePublicId==input.SourceLinePublicId.Value,ct);
            if(line is null)return new(ErrorCategory.NotFound,"sales.invoice.source_line","Order source line was not found.");
            sourceQty=line.Quantity;
        }

        var used=await (
            from link in dbContext.Set<SalesInvoiceSourceLinkRecord>().AsNoTracking()
            join invoiceLine in dbContext.Set<SalesInvoiceLineRecord>().AsNoTracking() on link.SalesInvoiceLineId equals invoiceLine.Id
            join invoice in dbContext.Set<SalesInvoiceRecord>().AsNoTracking() on invoiceLine.SalesInvoiceId equals invoice.Id
            where link.CompanyId==companyId&&invoice.CompanyId==companyId&&invoice.State==SalesInvoiceState.Draft&&
                  (!excludeInvoicePublicId.HasValue || invoice.PublicId!=excludeInvoicePublicId.Value)&&
                  link.SourceMode==mode&&link.SourceDocumentPublicId==input.SourceDocumentPublicId&&link.SourceLinePublicId==input.SourceLinePublicId
            select (decimal?)link.Quantity).SumAsync(ct)??0m;
        if(used+input.Quantity>sourceQty)
            return new(ErrorCategory.BusinessRule,"sales.invoice.source_cap","Cumulative Invoice draft quantity exceeds source quantity.");
        return null;
    }

    private Task<Result<SalesMutationReceipt>> ChangeDispatchState(
        Guid id,long version,string key,IExecutionContext context,DispatchState expected,DispatchState target,
        string scope,string action,string state,CancellationToken ct) =>
        MutateAsync(scope,key,action,"Dispatch",$"{scope}.completed",context,
            async innerCt=>{
                var d=await LockDispatchAsync(id,context.CompanyId,innerCt);
                if(d is null)return NotFound<SalesMutationReceipt>("sales.dispatch.not_found","Dispatch was not found.");
                if(d.Version!=version)return Conflict<SalesMutationReceipt>("sales.dispatch.stale","Dispatch version is stale.",true);
                if(d.State!=expected)return Business<SalesMutationReceipt>("sales.dispatch.state","Dispatch is not in the required state.");
                d.State=target;d.Version++;
                if(target==DispatchState.HandedOver)d.HandedOverAt=DateTimeOffset.UtcNow;
                if(target==DispatchState.Delivered)d.DeliveredAt=DateTimeOffset.UtcNow;
                return Result<SalesMutationReceipt>.Success(new(d.PublicId,state,d.Version,context.CorrelationId.Value));
            },ct);

    private Task<DispatchRecord?> LockDispatchAsync(Guid id,Guid companyId,CancellationToken ct)=>
        dbContext.Set<DispatchRecord>().FromSqlInterpolated(
            $"SELECT * FROM sales.dispatches WHERE public_id = {id} AND company_id = {companyId} FOR UPDATE").SingleOrDefaultAsync(ct);
    private Task<DispatchRecord?> LockDispatchByIdAsync(long id,Guid companyId,CancellationToken ct)=>
        dbContext.Set<DispatchRecord>().FromSqlInterpolated(
            $"SELECT * FROM sales.dispatches WHERE id = {id} AND company_id = {companyId} FOR UPDATE").SingleOrDefaultAsync(ct);

    private Task<LocationRecord?> ResolveLocationAsync(Guid companyId,long warehouseId,Guid? publicId,CancellationToken ct)=>
        !publicId.HasValue?Task.FromResult<LocationRecord?>(null):
        dbContext.Set<LocationRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.WarehouseId==warehouseId&&x.PublicId==publicId.Value&&x.State==InventoryMasterState.Active,ct);
    private Task<InventoryLotRecord?> ResolveLotAsync(Guid companyId,SalesOrderLineRecord line,Guid? publicId,CancellationToken ct)=>
        !publicId.HasValue?Task.FromResult<InventoryLotRecord?>(null):
        dbContext.Set<InventoryLotRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==publicId.Value&&x.ProductId==line.ProductId&&x.VariantId==line.VariantId,ct);
    private Task<InventorySerialRecord?> ResolveSerialAsync(Guid companyId,SalesOrderLineRecord line,Guid? publicId,long? lotId,CancellationToken ct)=>
        !publicId.HasValue?Task.FromResult<InventorySerialRecord?>(null):
        dbContext.Set<InventorySerialRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==publicId.Value&&x.ProductId==line.ProductId&&x.VariantId==line.VariantId&&x.LotId==lotId,ct);

    private async Task<string?> AddressSnapshotAsync(long partyId,Guid companyId,PartyAddressPurpose purpose,CancellationToken ct)
    {
        var a=await dbContext.Set<PartyAddressRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&x.PartyId==partyId&&x.Purpose==purpose&&x.State==PartyMasterRecordState.Active)
            .OrderByDescending(x=>x.IsDefault).ThenBy(x=>x.Id).FirstOrDefaultAsync(ct);
        if(a is null)return null;
        return string.Join(", ",new[]{a.Line1,a.Line2,a.District,a.City,a.PostalCode,a.Country}.Where(x=>!string.IsNullOrWhiteSpace(x)));
    }
}
