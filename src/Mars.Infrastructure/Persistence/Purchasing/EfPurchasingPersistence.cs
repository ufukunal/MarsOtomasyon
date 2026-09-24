using System.Text.Json;
using Mars.Application.Foundation.Approvals;
using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Outbox;
using Mars.Application.Foundation.Results;
using Mars.Application.Purchasing;
using Mars.Domain.Inventory;
using Mars.Domain.Parties;
using Mars.Domain.Products;
using Mars.Domain.Purchasing;
using Mars.Infrastructure.Persistence.Foundation;
using Mars.Infrastructure.Persistence.Inventory;
using Mars.Infrastructure.Persistence.Parties;
using Mars.Infrastructure.Persistence.Products;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;
using Npgsql;

namespace Mars.Infrastructure.Persistence.Purchasing;

public sealed class EfPurchasingPersistence(
    MarsDbContext dbContext,
    IAuditWriter auditWriter,
    IIdempotencyStore idempotencyStore,
    IOutboxWriter outboxWriter)
    : IPurchasingPersistence
{
    private sealed record ResolvedSupplier(long Id, string Code, string LegalName);
    private sealed record ResolvedTrade(
        long ProductId,
        long? VariantId,
        long UomId,
        decimal ConversionFactor,
        Guid ProductPublicId,
        Guid? VariantPublicId,
        Guid UomPublicId,
        string ProductCode,
        string ProductName,
        string? VariantCode,
        string? VariantName,
        string UomCode,
        string UomName,
        bool Stockable);

    public async Task<IReadOnlyList<PurchasingDocumentListItem>> ListOrdersAsync(
        Guid companyId, CancellationToken ct) =>
        await dbContext.Set<PurchaseOrderRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId)
            .OrderByDescending(x=>x.CreatedAt).ThenByDescending(x=>x.Id)
            .Take(250)
            .Select(x=>new PurchasingDocumentListItem(
                x.PublicId,x.Number,OrderStateCode(x.State),
                x.SupplierCodeSnapshot,x.SupplierNameSnapshot,x.Version,x.CreatedAt))
            .ToArrayAsync(ct);

    public async Task<PurchasingDocumentDetailView?> GetOrderAsync(
        Guid companyId, Guid publicId, CancellationToken ct)
    {
        var order=await dbContext.Set<PurchaseOrderRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==publicId,ct);
        if(order is null) return null;
        var version=await dbContext.Set<PurchaseOrderVersionRecord>().AsNoTracking()
            .SingleAsync(x=>x.CompanyId==companyId&&x.PurchaseOrderId==order.Id&&
                            x.VersionNumber==order.CurrentVersionNumber,ct);
        var lines=await dbContext.Set<PurchaseOrderLineRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&x.PurchaseOrderVersionId==version.Id)
            .OrderBy(x=>x.Sequence).ToArrayAsync(ct);
        var processed=await NetReceivedAsync(companyId,order.Id,lines.Select(x=>x.LinePublicId).ToArray(),ct);
        return new PurchasingDocumentDetailView(
            order.PublicId,order.Number,OrderStateCode(order.State),
            order.SupplierCodeSnapshot,order.SupplierNameSnapshot,order.CurrencyCode,order.PaymentTerms,order.Version,
            lines.Select(x=>{
                var done=processed.GetValueOrDefault(x.LinePublicId);
                return new PurchasingDocumentLineView(
                    x.LinePublicId,x.Sequence,x.ProductCodeSnapshot,x.ProductNameSnapshot,x.UomCodeSnapshot,
                    x.Quantity,done,x.Quantity-done,x.UnitPrice,x.LineDiscountPercent,x.TaxPercent);
            }).ToArray());
    }

    public async Task<IReadOnlyList<PurchasingDocumentListItem>> ListReceiptsAsync(
        Guid companyId, CancellationToken ct) =>
        await (
            from r in dbContext.Set<GoodsReceiptRecord>().AsNoTracking()
            join o in dbContext.Set<PurchaseOrderRecord>().AsNoTracking() on r.PurchaseOrderId equals o.Id
            where r.CompanyId==companyId && o.CompanyId==companyId
            orderby r.CreatedAt descending,r.Id descending
            select new PurchasingDocumentListItem(
                r.PublicId,r.Number,ReceiptStateCode(r.State),
                o.SupplierCodeSnapshot,o.SupplierNameSnapshot,r.Version,r.CreatedAt))
            .Take(250).ToArrayAsync(ct);

    public async Task<PurchasingDocumentDetailView?> GetReceiptAsync(
        Guid companyId, Guid publicId, CancellationToken ct)
    {
        var receipt=await dbContext.Set<GoodsReceiptRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==publicId,ct);
        if(receipt is null) return null;
        var order=await dbContext.Set<PurchaseOrderRecord>().AsNoTracking()
            .SingleAsync(x=>x.CompanyId==companyId&&x.Id==receipt.PurchaseOrderId,ct);
        var lines=await dbContext.Set<GoodsReceiptLineRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&x.GoodsReceiptId==receipt.Id)
            .OrderBy(x=>x.Sequence).ToArrayAsync(ct);
        var products=await dbContext.Set<ProductRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&lines.Select(l=>l.ProductId).Contains(x.Id))
            .ToDictionaryAsync(x=>x.Id,ct);
        var uoms=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&lines.Select(l=>l.UomId).Contains(x.Id))
            .ToDictionaryAsync(x=>x.Id,ct);
        return new PurchasingDocumentDetailView(
            receipt.PublicId,receipt.Number,ReceiptStateCode(receipt.State),
            order.SupplierCodeSnapshot,order.SupplierNameSnapshot,order.CurrencyCode,order.PaymentTerms,receipt.Version,
            lines.Select(x=>new PurchasingDocumentLineView(
                x.PublicId,x.Sequence,products[x.ProductId].ProductCode,products[x.ProductId].Name,uoms[x.UomId].Code,
                x.Quantity,receipt.State==GoodsReceiptState.Posted?x.Quantity:0m,
                receipt.State==GoodsReceiptState.Posted?0m:x.Quantity,0m,0m,0m)).ToArray());
    }

    public async Task<IReadOnlyList<PurchasingDocumentListItem>> ListInvoicesAsync(
        Guid companyId, CancellationToken ct) =>
        await dbContext.Set<SupplierInvoiceRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId)
            .OrderByDescending(x=>x.CreatedAt).ThenByDescending(x=>x.Id)
            .Take(250)
            .Select(x=>new PurchasingDocumentListItem(
                x.PublicId,x.Number,InvoiceStateCode(x.State),
                x.SupplierCodeSnapshot,x.SupplierLegalNameSnapshot,x.Version,x.CreatedAt))
            .ToArrayAsync(ct);

    public Task<Result<PurchasingMutationReceipt>> CreateOrderAsync(
        CreatePurchaseOrderCommand command,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("purchasing.order.create",command.OperationKey,"PurchaseOrderCreated","PurchaseOrder",
            "purchasing.order.create.completed",context,
            async innerCt=>{
                var invalid=ValidateDocument(command.Number,command.CurrencyCode,command.DocumentDiscountPercent,command.Lines);
                if(invalid is not null)return Result<PurchasingMutationReceipt>.Failure(invalid);
                var supplier=await ResolveSupplierAsync(context.CompanyId,command.SupplierPartyPublicId,innerCt);
                if(supplier is null)return Business<PurchasingMutationReceipt>(
                    "purchasing.supplier.not_eligible","Supplier must be ACTIVE with an ACTIVE Supplier role.");
                var resolved=await ResolveLinesAsync(context.CompanyId,command.Lines,innerCt);
                if(resolved.IsFailure)return Result<PurchasingMutationReceipt>.Failure(resolved.Error!);

                var now=DateTimeOffset.UtcNow;
                var order=new PurchaseOrderRecord{
                    PublicId=Guid.NewGuid(),CompanyId=context.CompanyId,Number=command.Number.Trim(),
                    SupplierPartyId=supplier.Id,SupplierCodeSnapshot=supplier.Code,SupplierNameSnapshot=supplier.LegalName,
                    CurrencyCode=command.CurrencyCode.Trim().ToUpperInvariant(),PaymentTerms=Normalize(command.PaymentTerms),
                    State=PurchaseOrderState.Draft,CurrentVersionNumber=1,Version=1,
                    CreatorActorId=context.ActorId,CreatedAt=now
                };
                dbContext.Add(order);
                await dbContext.SaveChangesAsync(innerCt);
                var version=new PurchaseOrderVersionRecord{
                    PublicId=Guid.NewGuid(),PurchaseOrderId=order.Id,CompanyId=context.CompanyId,
                    VersionNumber=1,DocumentDiscountPercent=command.DocumentDiscountPercent,
                    CreatorActorId=context.ActorId,CreatedAt=now
                };
                dbContext.Add(version);
                await dbContext.SaveChangesAsync(innerCt);
                for(var i=0;i<command.Lines.Count;i++){
                    var line=command.Lines[i]; var trade=resolved.Value![i];
                    dbContext.Add(new PurchaseOrderLineRecord{
                        LinePublicId=Guid.NewGuid(),PurchaseOrderVersionId=version.Id,CompanyId=context.CompanyId,
                        Sequence=line.Sequence,ProductId=trade.ProductId,VariantId=trade.VariantId,UomId=trade.UomId,
                        ConversionFactorSnapshot=trade.ConversionFactor,
                        ProductCodeSnapshot=trade.ProductCode,ProductNameSnapshot=trade.ProductName,
                        VariantCodeSnapshot=trade.VariantCode,VariantNameSnapshot=trade.VariantName,
                        UomCodeSnapshot=trade.UomCode,UomNameSnapshot=trade.UomName,
                        Quantity=line.Quantity,UnitPrice=line.UnitPrice,
                        LineDiscountPercent=line.LineDiscountPercent,TaxPercent=line.TaxPercent
                    });
                }
                return Result<PurchasingMutationReceipt>.Success(
                    new(order.PublicId,"DRAFT",order.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<PurchasingMutationReceipt>> ConfirmOrderAsync(
        Guid orderPublicId,long expectedVersion,string operationKey,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("purchasing.order.confirm",operationKey,"PurchaseOrderConfirmed","PurchaseOrder",
            "purchasing.order.confirm.completed",context,
            async innerCt=>{
                var order=await dbContext.Set<PurchaseOrderRecord>()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==orderPublicId,innerCt);
                if(order is null)return NotFound<PurchasingMutationReceipt>("purchasing.order.not_found","Purchase Order was not found.");
                if(order.Version!=expectedVersion)return Conflict<PurchasingMutationReceipt>("purchasing.order.stale","Purchase Order version is stale.",true);
                if(order.State!=PurchaseOrderState.Draft)return Business<PurchasingMutationReceipt>(
                    "purchasing.order.state","Only DRAFT Purchase Order can be confirmed in the current tranche.");
                order.State=PurchaseOrderState.Confirmed;
                order.Version++;
                order.ConfirmedAt=DateTimeOffset.UtcNow;
                return Result<PurchasingMutationReceipt>.Success(new(order.PublicId,"CONFIRMED",order.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<PurchasingMutationReceipt>> AmendOrderAsync(
        AmendPurchaseOrderCommand command,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("purchasing.order.amend",command.OperationKey,"PurchaseOrderAmended","PurchaseOrder",
            "purchasing.order.amend.completed",context,
            async innerCt=>{
                if(command.PurchaseOrderPublicId==Guid.Empty||command.ExpectedVersion<=0||
                   string.IsNullOrWhiteSpace(command.Reason)||command.Deltas.Count==0||
                   command.Deltas.Select(x=>x.PurchaseOrderLinePublicId).Distinct().Count()!=command.Deltas.Count)
                    return Validation<PurchasingMutationReceipt>("purchasing.order.amend.invalid","Order, version, reason and unique amendment deltas are required.");
                if(command.Deltas.Any(x=>x.PurchaseOrderLinePublicId==Guid.Empty||x.QuantityDelta>=0m))
                    return Business<PurchasingMutationReceipt>("purchasing.order.amend.policy_required",
                        "Without an authoritative Purchasing Policy, only quantity decreases of unprocessed remainder are allowed.");

                var order=await dbContext.Set<PurchaseOrderRecord>()
                    .FromSqlInterpolated($@"SELECT * FROM purchasing.purchase_orders WHERE company_id={context.CompanyId} AND public_id={command.PurchaseOrderPublicId} FOR UPDATE")
                    .SingleOrDefaultAsync(innerCt);
                if(order is null)return NotFound<PurchasingMutationReceipt>("purchasing.order.not_found","Purchase Order was not found.");
                if(order.Version!=command.ExpectedVersion)
                    return Conflict<PurchasingMutationReceipt>("purchasing.order.stale","Purchase Order version is stale.",true);
                if(order.State is not (PurchaseOrderState.Confirmed or PurchaseOrderState.PartiallyReceived))
                    return Business<PurchasingMutationReceipt>("purchasing.order.amend.state","Only confirmed/open Purchase Order can be amended.");

                var current=await dbContext.Set<PurchaseOrderVersionRecord>().AsNoTracking()
                    .SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PurchaseOrderId==order.Id&&x.VersionNumber==order.CurrentVersionNumber,innerCt);
                var lines=await dbContext.Set<PurchaseOrderLineRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==context.CompanyId&&x.PurchaseOrderVersionId==current.Id)
                    .OrderBy(x=>x.Sequence).ToArrayAsync(innerCt);
                var byId=lines.ToDictionary(x=>x.LinePublicId);
                var received=await NetReceivedAsync(context.CompanyId,order.Id,lines.Select(x=>x.LinePublicId).ToArray(),innerCt);

                foreach(var delta in command.Deltas){
                    if(!byId.TryGetValue(delta.PurchaseOrderLinePublicId,out var line))
                        return Business<PurchasingMutationReceipt>("purchasing.order.amend.line","Amendment line must belong to the effective Purchase Order version.");
                    var next=line.Quantity+delta.QuantityDelta;
                    if(next<=0m||next<received.GetValueOrDefault(line.LinePublicId))
                        return Business<PurchasingMutationReceipt>("purchasing.order.amend.processed_floor",
                            "Amendment cannot reduce quantity to zero or below already POSTED receipt quantity.");
                }

                var now=DateTimeOffset.UtcNow;
                var resultVersion=checked(order.CurrentVersionNumber+1);
                var amendment=new PurchaseOrderAmendmentRecord{
                    PublicId=Guid.NewGuid(),PurchaseOrderId=order.Id,CompanyId=context.CompanyId,
                    BaseVersionNumber=order.CurrentVersionNumber,ResultVersionNumber=resultVersion,
                    State=PurchaseOrderAmendmentState.Active,Reason=command.Reason.Trim(),
                    CreatorActorId=context.ActorId,CreatedAt=now,ActivatedAt=now
                };
                dbContext.Add(amendment);
                var version=new PurchaseOrderVersionRecord{
                    PublicId=Guid.NewGuid(),PurchaseOrderId=order.Id,CompanyId=context.CompanyId,
                    VersionNumber=resultVersion,DocumentDiscountPercent=current.DocumentDiscountPercent,
                    CreatorActorId=context.ActorId,CreatedAt=now
                };
                dbContext.Add(version);
                await dbContext.SaveChangesAsync(innerCt);

                var deltas=command.Deltas.ToDictionary(x=>x.PurchaseOrderLinePublicId);
                foreach(var old in lines){
                    var quantity=old.Quantity;
                    if(deltas.TryGetValue(old.LinePublicId,out var delta)){
                        quantity+=delta.QuantityDelta;
                        dbContext.Add(new PurchaseOrderAmendmentDeltaRecord{
                            PublicId=Guid.NewGuid(),AmendmentId=amendment.Id,CompanyId=context.CompanyId,
                            PurchaseOrderLinePublicId=old.LinePublicId,QuantityDelta=delta.QuantityDelta
                        });
                    }
                    dbContext.Add(new PurchaseOrderLineRecord{
                        LinePublicId=old.LinePublicId,PurchaseOrderVersionId=version.Id,CompanyId=context.CompanyId,
                        Sequence=old.Sequence,ProductId=old.ProductId,VariantId=old.VariantId,UomId=old.UomId,
                        ConversionFactorSnapshot=old.ConversionFactorSnapshot,ProductCodeSnapshot=old.ProductCodeSnapshot,
                        ProductNameSnapshot=old.ProductNameSnapshot,VariantCodeSnapshot=old.VariantCodeSnapshot,
                        VariantNameSnapshot=old.VariantNameSnapshot,UomCodeSnapshot=old.UomCodeSnapshot,UomNameSnapshot=old.UomNameSnapshot,
                        Quantity=quantity,UnitPrice=old.UnitPrice,LineDiscountPercent=old.LineDiscountPercent,TaxPercent=old.TaxPercent
                    });
                }
                order.CurrentVersionNumber=resultVersion;
                order.Version++;
                return Result<PurchasingMutationReceipt>.Success(new(order.PublicId,OrderStateCode(order.State),order.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<PurchasingMutationReceipt>> CancelOrderRemainderAsync(
        Guid orderPublicId,long expectedVersion,string reason,string operationKey,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("purchasing.order.cancel_remaining",operationKey,"PurchaseOrderRemainderCancelled","PurchaseOrder",
            "purchasing.order.cancel_remaining.completed",context,
            async innerCt=>{
                if(string.IsNullOrWhiteSpace(reason))
                    return Validation<PurchasingMutationReceipt>("purchasing.order.cancel.reason","Cancellation reason is required.");
                var order=await dbContext.Set<PurchaseOrderRecord>()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==orderPublicId,innerCt);
                if(order is null)return NotFound<PurchasingMutationReceipt>("purchasing.order.not_found","Purchase Order was not found.");
                if(order.Version!=expectedVersion)return Conflict<PurchasingMutationReceipt>("purchasing.order.stale","Purchase Order version is stale.",true);
                if(order.State is not (PurchaseOrderState.Draft or PurchaseOrderState.Confirmed or PurchaseOrderState.PartiallyReceived))
                    return Business<PurchasingMutationReceipt>("purchasing.order.cancel.state","Purchase Order has no cancellable remainder.");
                order.State=order.State==PurchaseOrderState.Draft?PurchaseOrderState.Cancelled:PurchaseOrderState.CancelledRemainder;
                order.Version++;
                order.ClosedAt=DateTimeOffset.UtcNow;
                return Result<PurchasingMutationReceipt>.Success(new(
                    order.PublicId,OrderStateCode(order.State),order.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<PurchasingMutationReceipt>> CloseOrderAsync(
        Guid orderPublicId,long expectedVersion,string operationKey,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("purchasing.order.close",operationKey,"PurchaseOrderClosed","PurchaseOrder",
            "purchasing.order.close.completed",context,
            async innerCt=>{
                var order=await dbContext.Set<PurchaseOrderRecord>()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==orderPublicId,innerCt);
                if(order is null)return NotFound<PurchasingMutationReceipt>("purchasing.order.not_found","Purchase Order was not found.");
                if(order.Version!=expectedVersion)return Conflict<PurchasingMutationReceipt>("purchasing.order.stale","Purchase Order version is stale.",true);
                if(order.State==PurchaseOrderState.CancelledRemainder){
                    order.State=PurchaseOrderState.Completed;order.Version++;order.ClosedAt=DateTimeOffset.UtcNow;
                    return Result<PurchasingMutationReceipt>.Success(new(order.PublicId,"COMPLETED",order.Version,context.CorrelationId.Value));
                }
                if(order.State is not (PurchaseOrderState.Confirmed or PurchaseOrderState.PartiallyReceived))
                    return Business<PurchasingMutationReceipt>("purchasing.order.close.state","Purchase Order is not closeable.");
                var version=await dbContext.Set<PurchaseOrderVersionRecord>().AsNoTracking()
                    .SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PurchaseOrderId==order.Id&&x.VersionNumber==order.CurrentVersionNumber,innerCt);
                var lines=await dbContext.Set<PurchaseOrderLineRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==context.CompanyId&&x.PurchaseOrderVersionId==version.Id).ToArrayAsync(innerCt);
                var received=await NetReceivedAsync(context.CompanyId,order.Id,lines.Select(x=>x.LinePublicId).ToArray(),innerCt);
                if(lines.Any(x=>received.GetValueOrDefault(x.LinePublicId)<x.Quantity))
                    return Business<PurchasingMutationReceipt>("purchasing.order.close.remaining","Open receipt remainder must be received or explicitly cancelled before close.");
                order.State=PurchaseOrderState.Completed;order.Version++;order.ClosedAt=DateTimeOffset.UtcNow;
                return Result<PurchasingMutationReceipt>.Success(new(order.PublicId,"COMPLETED",order.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<PurchasingMutationReceipt>> CreateReceiptAsync(
        CreateGoodsReceiptCommand command,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("purchasing.receipt.create",command.OperationKey,"GoodsReceiptCreated","GoodsReceipt",
            "purchasing.receipt.create.completed",context,
            async innerCt=>{
                if(string.IsNullOrWhiteSpace(command.Number)||command.PurchaseOrderPublicId==Guid.Empty||
                   command.PurchaseOrderVersion<=0||command.WarehousePublicId==Guid.Empty||command.Lines.Count==0||
                   command.Lines.Any(x=>x.Sequence<=0||x.PurchaseOrderLinePublicId==Guid.Empty||x.Quantity<=0m))
                    return Validation<PurchasingMutationReceipt>("purchasing.receipt.invalid","Receipt identity, source and positive lines are required.");

                var order=await dbContext.Set<PurchaseOrderRecord>().AsNoTracking()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==command.PurchaseOrderPublicId,innerCt);
                if(order is null)return NotFound<PurchasingMutationReceipt>("purchasing.order.not_found","Purchase Order was not found.");
                if(order.State is not (PurchaseOrderState.Confirmed or PurchaseOrderState.PartiallyReceived))
                    return Business<PurchasingMutationReceipt>("purchasing.receipt.order_state","Receipt requires a confirmed Purchase Order.");
                if(order.CurrentVersionNumber!=command.PurchaseOrderVersion)
                    return Conflict<PurchasingMutationReceipt>("purchasing.receipt.order_version","Receipt source version is stale.",true);

                var warehouse=await dbContext.Set<WarehouseRecord>().AsNoTracking()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==command.WarehousePublicId&&x.State==InventoryMasterState.Active,innerCt);
                if(warehouse is null)return Business<PurchasingMutationReceipt>("purchasing.receipt.warehouse","Warehouse must be ACTIVE.");

                var version=await dbContext.Set<PurchaseOrderVersionRecord>().AsNoTracking()
                    .SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PurchaseOrderId==order.Id&&x.VersionNumber==command.PurchaseOrderVersion,innerCt);
                var orderLines=await dbContext.Set<PurchaseOrderLineRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==context.CompanyId&&x.PurchaseOrderVersionId==version.Id)
                    .ToArrayAsync(innerCt);
                var byId=orderLines.ToDictionary(x=>x.LinePublicId);
                var existing=await NetCommittedReceiptAsync(context.CompanyId,order.Id,orderLines.Select(x=>x.LinePublicId).ToArray(),innerCt);

                foreach(var input in command.Lines){
                    if(!byId.TryGetValue(input.PurchaseOrderLinePublicId,out var line))
                        return Business<PurchasingMutationReceipt>("purchasing.receipt.source_line","Receipt line must reference the exact Purchase Order version.");
                    if(existing.GetValueOrDefault(line.LinePublicId)+input.Quantity>line.Quantity)
                        return Business<PurchasingMutationReceipt>("purchasing.receipt.over_receipt","Over-receipt is blocked without an authoritative tolerance policy.");
                }

                var now=DateTimeOffset.UtcNow;
                var receipt=new GoodsReceiptRecord{
                    PublicId=Guid.NewGuid(),CompanyId=context.CompanyId,Number=command.Number.Trim(),
                    PurchaseOrderId=order.Id,PurchaseOrderVersionNumber=command.PurchaseOrderVersion,
                    WarehouseId=warehouse.Id,State=GoodsReceiptState.Draft,Version=1,
                    CreatorActorId=context.ActorId,CreatedAt=now
                };
                dbContext.Add(receipt);
                await dbContext.SaveChangesAsync(innerCt);

                foreach(var input in command.Lines){
                    var line=byId[input.PurchaseOrderLinePublicId];
                    long? locationId=null,lotId=null,serialId=null;
                    if(input.LocationPublicId.HasValue){
                        var location=await dbContext.Set<LocationRecord>().AsNoTracking()
                            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==input.LocationPublicId.Value&&
                                x.WarehouseId==warehouse.Id&&x.State==InventoryMasterState.Active&&x.StockBearing,innerCt);
                        if(location is null)return Business<PurchasingMutationReceipt>("purchasing.receipt.location","Location must be ACTIVE, stock-bearing and belong to the receipt Warehouse.");
                        locationId=location.Id;
                    }
                    if(input.LotPublicId.HasValue){
                        var lot=await dbContext.Set<InventoryLotRecord>().AsNoTracking()
                            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==input.LotPublicId.Value&&
                                x.ProductId==line.ProductId&&x.VariantId==line.VariantId,innerCt);
                        if(lot is null)return Business<PurchasingMutationReceipt>("purchasing.receipt.lot","Lot must match the Purchase Order Product/Variant.");
                        lotId=lot.Id;
                    }
                    if(input.SerialPublicId.HasValue){
                        var serial=await dbContext.Set<InventorySerialRecord>().AsNoTracking()
                            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==input.SerialPublicId.Value&&
                                x.ProductId==line.ProductId&&x.VariantId==line.VariantId,innerCt);
                        if(serial is null)return Business<PurchasingMutationReceipt>("purchasing.receipt.serial","Serial must match the Purchase Order Product/Variant.");
                        serialId=serial.Id;
                    }
                    dbContext.Add(new GoodsReceiptLineRecord{
                        PublicId=Guid.NewGuid(),GoodsReceiptId=receipt.Id,CompanyId=context.CompanyId,
                        Sequence=input.Sequence,PurchaseOrderLinePublicId=line.LinePublicId,
                        ProductId=line.ProductId,VariantId=line.VariantId,UomId=line.UomId,
                        ConversionFactorSnapshot=line.ConversionFactorSnapshot,Quantity=input.Quantity,
                        WarehouseId=warehouse.Id,LocationId=locationId,LotId=lotId,SerialId=serialId
                    });
                }
                return Result<PurchasingMutationReceipt>.Success(new(receipt.PublicId,"DRAFT",receipt.Version,context.CorrelationId.Value));
            },ct);

    public async Task<Guid?> GetReceiptWarehousePublicIdAsync(
        Guid companyId,Guid receiptPublicId,CancellationToken ct) =>
        await (
            from r in dbContext.Set<GoodsReceiptRecord>().AsNoTracking()
            join w in dbContext.Set<WarehouseRecord>().AsNoTracking() on r.WarehouseId equals w.Id
            where r.CompanyId==companyId&&w.CompanyId==companyId&&r.PublicId==receiptPublicId
            select (Guid?)w.PublicId).SingleOrDefaultAsync(ct);

    public Task<Result<PurchasingMutationReceipt>> ReadyReceiptAsync(
        Guid receiptPublicId,long expectedVersion,string operationKey,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("purchasing.receipt.ready",operationKey,"GoodsReceiptReadied","GoodsReceipt",
            "purchasing.receipt.ready.completed",context,
            async innerCt=>{
                var receipt=await dbContext.Set<GoodsReceiptRecord>()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==receiptPublicId,innerCt);
                if(receipt is null)return NotFound<PurchasingMutationReceipt>("purchasing.receipt.not_found","Goods Receipt was not found.");
                if(receipt.Version!=expectedVersion)return Conflict<PurchasingMutationReceipt>("purchasing.receipt.stale","Goods Receipt version is stale.",true);
                if(receipt.State!=GoodsReceiptState.Draft)
                    return Business<PurchasingMutationReceipt>("purchasing.receipt.state","Only DRAFT Goods Receipt can become READY.");
                receipt.State=GoodsReceiptState.Ready;receipt.Version++;
                return Result<PurchasingMutationReceipt>.Success(new(receipt.PublicId,"READY",receipt.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<PurchasingMutationReceipt>> CancelReceiptAsync(
        Guid receiptPublicId,long expectedVersion,string reason,string operationKey,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("purchasing.receipt.cancel",operationKey,"GoodsReceiptCancelled","GoodsReceipt",
            "purchasing.receipt.cancel.completed",context,
            async innerCt=>{
                if(string.IsNullOrWhiteSpace(reason))
                    return Validation<PurchasingMutationReceipt>("purchasing.receipt.cancel.reason","Cancellation reason is required.");
                var receipt=await dbContext.Set<GoodsReceiptRecord>()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==receiptPublicId,innerCt);
                if(receipt is null)return NotFound<PurchasingMutationReceipt>("purchasing.receipt.not_found","Goods Receipt was not found.");
                if(receipt.Version!=expectedVersion)return Conflict<PurchasingMutationReceipt>("purchasing.receipt.stale","Goods Receipt version is stale.",true);
                if(receipt.State is not (GoodsReceiptState.Draft or GoodsReceiptState.Ready))
                    return Business<PurchasingMutationReceipt>("purchasing.receipt.cancel.state","Only pre-POST Goods Receipt can be cancelled.");
                receipt.State=GoodsReceiptState.Cancelled;receipt.Version++;receipt.CancelledAt=DateTimeOffset.UtcNow;
                return Result<PurchasingMutationReceipt>.Success(new(receipt.PublicId,"CANCELLED",receipt.Version,context.CorrelationId.Value));
            },ct);

    public async Task<Result<GoodsReceiptPostPlan>> PrepareReceiptPostAsync(
        Guid receiptPublicId,IExecutionContext context,CancellationToken ct)
    {
        var receipt=await dbContext.Set<GoodsReceiptRecord>()
            .FromSqlInterpolated($@"SELECT * FROM purchasing.goods_receipts WHERE company_id={context.CompanyId} AND public_id={receiptPublicId} FOR UPDATE")
            .SingleOrDefaultAsync(ct);
        if(receipt is null)return NotFound<GoodsReceiptPostPlan>("purchasing.receipt.not_found","Goods Receipt was not found.");
        if(receipt.State is not (GoodsReceiptState.Draft or GoodsReceiptState.Ready))
            return Business<GoodsReceiptPostPlan>("purchasing.receipt.state","Only DRAFT/READY Goods Receipt can be posted.");

        var order=await dbContext.Set<PurchaseOrderRecord>()
            .FromSqlInterpolated($@"SELECT * FROM purchasing.purchase_orders WHERE company_id={context.CompanyId} AND id={receipt.PurchaseOrderId} FOR UPDATE")
            .SingleAsync(ct);
        if(order.CurrentVersionNumber!=receipt.PurchaseOrderVersionNumber)
            return Conflict<GoodsReceiptPostPlan>("purchasing.receipt.order_version","Goods Receipt source version is stale.",true);

        var lines=await dbContext.Set<GoodsReceiptLineRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&x.GoodsReceiptId==receipt.Id)
            .OrderBy(x=>x.Sequence).ToArrayAsync(ct);
        var products=await dbContext.Set<ProductRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Select(l=>l.ProductId).Contains(x.Id))
            .ToDictionaryAsync(x=>x.Id,ct);
        var variants=await dbContext.Set<ProductVariantRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Where(l=>l.VariantId.HasValue).Select(l=>l.VariantId!.Value).Contains(x.Id))
            .ToDictionaryAsync(x=>x.Id,ct);
        var uoms=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Select(l=>l.UomId).Contains(x.Id))
            .ToDictionaryAsync(x=>x.Id,ct);
        var warehouses=await dbContext.Set<WarehouseRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Select(l=>l.WarehouseId).Contains(x.Id))
            .ToDictionaryAsync(x=>x.Id,ct);
        var locations=await dbContext.Set<LocationRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Where(l=>l.LocationId.HasValue).Select(l=>l.LocationId!.Value).Contains(x.Id))
            .ToDictionaryAsync(x=>x.Id,ct);
        var lots=await dbContext.Set<InventoryLotRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Where(l=>l.LotId.HasValue).Select(l=>l.LotId!.Value).Contains(x.Id))
            .ToDictionaryAsync(x=>x.Id,ct);
        var serials=await dbContext.Set<InventorySerialRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Where(l=>l.SerialId.HasValue).Select(l=>l.SerialId!.Value).Contains(x.Id))
            .ToDictionaryAsync(x=>x.Id,ct);

        return Result<GoodsReceiptPostPlan>.Success(new(
            receipt.PublicId,
            warehouses[receipt.WarehouseId].PublicId,
            lines.Select(x=>new GoodsReceiptPostLinePlan(
                x.PublicId,products[x.ProductId].PublicId,
                x.VariantId.HasValue?variants[x.VariantId.Value].PublicId:null,
                uoms[x.UomId].PublicId,x.Quantity,x.ConversionFactorSnapshot,
                warehouses[x.WarehouseId].PublicId,
                x.LocationId.HasValue?locations[x.LocationId.Value].PublicId:null,
                x.LotId.HasValue?lots[x.LotId.Value].PublicId:null,
                x.SerialId.HasValue?serials[x.SerialId.Value].PublicId:null,
                products[x.ProductId].Stockable)).ToArray()));
    }

    public Task<Result<PurchasingMutationReceipt>> CompleteReceiptPostAsync(
        Guid receiptPublicId,IReadOnlyList<GoodsReceiptInventoryEffect> effects,string operationKey,
        IExecutionContext context,CancellationToken ct) =>
        MutateAsync("purchasing.receipt.post",operationKey,"GoodsReceiptPosted","GoodsReceipt",
            "purchasing.receipt.post.completed",context,
            async innerCt=>{
                var receipt=await dbContext.Set<GoodsReceiptRecord>()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==receiptPublicId,innerCt);
                if(receipt is null)return NotFound<PurchasingMutationReceipt>("purchasing.receipt.not_found","Goods Receipt was not found.");
                if(receipt.State is not (GoodsReceiptState.Draft or GoodsReceiptState.Ready))
                    return Business<PurchasingMutationReceipt>("purchasing.receipt.state","Goods Receipt is not postable.");
                var lines=await dbContext.Set<GoodsReceiptLineRecord>()
                    .Where(x=>x.CompanyId==context.CompanyId&&x.GoodsReceiptId==receipt.Id).ToArrayAsync(innerCt);
                var productIds=lines.Select(x=>x.ProductId).Distinct().ToArray();
                var stockableIds=await dbContext.Set<ProductRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==context.CompanyId&&productIds.Contains(x.Id)&&x.Stockable)
                    .Select(x=>x.Id).ToArrayAsync(innerCt);
                var stockableLines=lines.Where(x=>stockableIds.Contains(x.ProductId)).ToArray();
                if(effects.Count!=stockableLines.Length||
                   effects.Select(x=>x.GoodsReceiptLinePublicId).Distinct().Count()!=stockableLines.Length||
                   stockableLines.Any(l=>effects.All(e=>e.GoodsReceiptLinePublicId!=l.PublicId))||
                   effects.Any(e=>stockableLines.All(l=>l.PublicId!=e.GoodsReceiptLinePublicId)))
                    return Business<PurchasingMutationReceipt>("purchasing.receipt.effects","Inventory effects must cover each STOCKABLE receipt line exactly once and no service/non-stock line.");

                var now=DateTimeOffset.UtcNow;
                foreach(var effect in effects){
                    var line=lines.Single(x=>x.PublicId==effect.GoodsReceiptLinePublicId);
                    dbContext.Add(new GoodsReceiptInventoryEffectLinkRecord{
                        PublicId=Guid.NewGuid(),GoodsReceiptLineId=line.Id,CompanyId=context.CompanyId,
                        InventoryMovementPublicId=effect.MovementPublicId,IsReversal=false,CreatedAt=now
                    });
                }
                receipt.State=GoodsReceiptState.Posted;
                receipt.Version++;
                receipt.PostedAt=now;
                var order=await dbContext.Set<PurchaseOrderRecord>()
                    .SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==receipt.PurchaseOrderId,innerCt);
                if(order.State==PurchaseOrderState.Confirmed)order.State=PurchaseOrderState.PartiallyReceived;
                return Result<PurchasingMutationReceipt>.Success(new(receipt.PublicId,"POSTED",receipt.Version,context.CorrelationId.Value));
            },ct);

    public async Task<Result<GoodsReceiptReversePlan>> PrepareReceiptReverseAsync(
        Guid receiptPublicId,IExecutionContext context,CancellationToken ct)
    {
        var receipt=await dbContext.Set<GoodsReceiptRecord>()
            .FromSqlInterpolated($@"SELECT * FROM purchasing.goods_receipts WHERE company_id={context.CompanyId} AND public_id={receiptPublicId} FOR UPDATE")
            .SingleOrDefaultAsync(ct);
        if(receipt is null)return NotFound<GoodsReceiptReversePlan>("purchasing.receipt.not_found","Goods Receipt was not found.");
        if(receipt.State!=GoodsReceiptState.Posted)
            return Business<GoodsReceiptReversePlan>("purchasing.receipt.reverse.state","Only POSTED Goods Receipt can be reversed.");

        var lines=await dbContext.Set<GoodsReceiptLineRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&x.GoodsReceiptId==receipt.Id).OrderBy(x=>x.Sequence).ToArrayAsync(ct);
        var products=await dbContext.Set<ProductRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Select(l=>l.ProductId).Contains(x.Id)).ToDictionaryAsync(x=>x.Id,ct);
        var variants=await dbContext.Set<ProductVariantRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Where(l=>l.VariantId.HasValue).Select(l=>l.VariantId!.Value).Contains(x.Id))
            .ToDictionaryAsync(x=>x.Id,ct);
        var uoms=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Select(l=>l.UomId).Contains(x.Id)).ToDictionaryAsync(x=>x.Id,ct);
        var warehouses=await dbContext.Set<WarehouseRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Select(l=>l.WarehouseId).Contains(x.Id)).ToDictionaryAsync(x=>x.Id,ct);
        var locations=await dbContext.Set<LocationRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Where(l=>l.LocationId.HasValue).Select(l=>l.LocationId!.Value).Contains(x.Id))
            .ToDictionaryAsync(x=>x.Id,ct);
        var lots=await dbContext.Set<InventoryLotRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Where(l=>l.LotId.HasValue).Select(l=>l.LotId!.Value).Contains(x.Id))
            .ToDictionaryAsync(x=>x.Id,ct);
        var serials=await dbContext.Set<InventorySerialRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Where(l=>l.SerialId.HasValue).Select(l=>l.SerialId!.Value).Contains(x.Id))
            .ToDictionaryAsync(x=>x.Id,ct);
        var lineIds=lines.Select(x=>x.Id).ToArray();
        var originals=await dbContext.Set<GoodsReceiptInventoryEffectLinkRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lineIds.Contains(x.GoodsReceiptLineId)&&!x.IsReversal)
            .ToDictionaryAsync(x=>x.GoodsReceiptLineId,x=>x.InventoryMovementPublicId,ct);

        foreach(var line in lines.Where(x=>products[x.ProductId].Stockable))
            if(!originals.ContainsKey(line.Id))
                return Business<GoodsReceiptReversePlan>("purchasing.receipt.reverse.effect_missing","STOCKABLE receipt line has no original Inventory movement link.");

        return Result<GoodsReceiptReversePlan>.Success(new(
            receipt.PublicId,
            warehouses[receipt.WarehouseId].PublicId,
            lines.Select(x=>new GoodsReceiptReverseLinePlan(
                x.PublicId,products[x.ProductId].PublicId,
                x.VariantId.HasValue?variants[x.VariantId.Value].PublicId:null,
                uoms[x.UomId].PublicId,x.Quantity,x.ConversionFactorSnapshot,
                warehouses[x.WarehouseId].PublicId,
                x.LocationId.HasValue?locations[x.LocationId.Value].PublicId:null,
                x.LotId.HasValue?lots[x.LotId.Value].PublicId:null,
                x.SerialId.HasValue?serials[x.SerialId.Value].PublicId:null,
                products[x.ProductId].Stockable?originals[x.Id]:Guid.Empty,
                products[x.ProductId].Stockable)).ToArray()));
    }

    public Task<Result<PurchasingMutationReceipt>> CompleteReceiptReverseAsync(
        Guid receiptPublicId,IReadOnlyList<GoodsReceiptInventoryEffect> effects,string operationKey,
        IExecutionContext context,CancellationToken ct) =>
        MutateAsync("purchasing.receipt.reverse",operationKey,"GoodsReceiptReversed","GoodsReceipt",
            "purchasing.receipt.reverse.completed",context,
            async innerCt=>{
                var receipt=await dbContext.Set<GoodsReceiptRecord>()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==receiptPublicId,innerCt);
                if(receipt is null)return NotFound<PurchasingMutationReceipt>("purchasing.receipt.not_found","Goods Receipt was not found.");
                if(receipt.State!=GoodsReceiptState.Posted)
                    return Business<PurchasingMutationReceipt>("purchasing.receipt.reverse.state","Goods Receipt is not reversible.");
                var lines=await dbContext.Set<GoodsReceiptLineRecord>()
                    .Where(x=>x.CompanyId==context.CompanyId&&x.GoodsReceiptId==receipt.Id).ToArrayAsync(innerCt);
                var productIds=lines.Select(x=>x.ProductId).Distinct().ToArray();
                var stockableIds=await dbContext.Set<ProductRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==context.CompanyId&&productIds.Contains(x.Id)&&x.Stockable)
                    .Select(x=>x.Id).ToArrayAsync(innerCt);
                var stockableLines=lines.Where(x=>stockableIds.Contains(x.ProductId)).ToArray();
                if(effects.Count!=stockableLines.Length||stockableLines.Any(l=>effects.All(e=>e.GoodsReceiptLinePublicId!=l.PublicId)))
                    return Business<PurchasingMutationReceipt>("purchasing.receipt.reverse.effects","Reversal effects must cover every STOCKABLE receipt line.");
                var originalByLine=await dbContext.Set<GoodsReceiptInventoryEffectLinkRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==context.CompanyId&&stockableLines.Select(l=>l.Id).Contains(x.GoodsReceiptLineId)&&!x.IsReversal)
                    .ToDictionaryAsync(x=>x.GoodsReceiptLineId,x=>x.InventoryMovementPublicId,innerCt);
                var now=DateTimeOffset.UtcNow;
                foreach(var effect in effects){
                    var line=stockableLines.Single(x=>x.PublicId==effect.GoodsReceiptLinePublicId);
                    dbContext.Add(new GoodsReceiptInventoryEffectLinkRecord{
                        PublicId=Guid.NewGuid(),GoodsReceiptLineId=line.Id,CompanyId=context.CompanyId,
                        InventoryMovementPublicId=effect.MovementPublicId,
                        OriginalInventoryMovementPublicId=originalByLine[line.Id],IsReversal=true,CreatedAt=now
                    });
                }
                receipt.State=GoodsReceiptState.Reversed;receipt.Version++;
                await dbContext.SaveChangesAsync(innerCt);
                var order=await dbContext.Set<PurchaseOrderRecord>()
                    .SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==receipt.PurchaseOrderId,innerCt);
                var version=await dbContext.Set<PurchaseOrderVersionRecord>().AsNoTracking()
                    .SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PurchaseOrderId==order.Id&&x.VersionNumber==order.CurrentVersionNumber,innerCt);
                var orderLines=await dbContext.Set<PurchaseOrderLineRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==context.CompanyId&&x.PurchaseOrderVersionId==version.Id).ToArrayAsync(innerCt);
                var net=await NetReceivedAsync(context.CompanyId,order.Id,orderLines.Select(x=>x.LinePublicId).ToArray(),innerCt);
                order.State=net.Values.Any(x=>x>0m)?PurchaseOrderState.PartiallyReceived:PurchaseOrderState.Confirmed;
                return Result<PurchasingMutationReceipt>.Success(new(receipt.PublicId,"REVERSED",receipt.Version,context.CorrelationId.Value));
            },ct);

    public Task<Result<PurchasingMutationReceipt>> CreateInvoiceDraftAsync(
        CreateSupplierInvoiceDraftCommand command,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("purchasing.invoice.create",command.OperationKey,"SupplierInvoiceDraftCreated","SupplierInvoice",
            "purchasing.invoice.create.completed",context,
            innerCt=>UpsertInvoiceDraftCoreAsync(null,null,command,context,innerCt),ct);

    public Task<Result<PurchasingMutationReceipt>> ReplaceInvoiceDraftAsync(
        Guid invoicePublicId,long expectedVersion,CreateSupplierInvoiceDraftCommand command,
        IExecutionContext context,CancellationToken ct) =>
        MutateAsync("purchasing.invoice.edit_draft",command.OperationKey,"SupplierInvoiceDraftReplaced","SupplierInvoice",
            "purchasing.invoice.edit_draft.completed",context,
            innerCt=>UpsertInvoiceDraftCoreAsync(invoicePublicId,expectedVersion,command,context,innerCt),ct);

    public Task<Result<PurchasingMutationReceipt>> CancelInvoiceDraftAsync(
        Guid invoicePublicId,long expectedVersion,string reason,string operationKey,IExecutionContext context,CancellationToken ct) =>
        MutateAsync("purchasing.invoice.cancel",operationKey,"SupplierInvoiceDraftCancelled","SupplierInvoice",
            "purchasing.invoice.cancel.completed",context,
            async innerCt=>{
                if(string.IsNullOrWhiteSpace(reason))
                    return Validation<PurchasingMutationReceipt>("purchasing.invoice.cancel.reason","Cancellation reason is required.");
                var invoice=await dbContext.Set<SupplierInvoiceRecord>()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==invoicePublicId,innerCt);
                if(invoice is null)return NotFound<PurchasingMutationReceipt>("purchasing.invoice.not_found","Supplier Invoice DRAFT was not found.");
                if(invoice.Version!=expectedVersion)return Conflict<PurchasingMutationReceipt>("purchasing.invoice.stale","Supplier Invoice version is stale.",true);
                if(invoice.State!=SupplierInvoiceState.Draft)
                    return Business<PurchasingMutationReceipt>("purchasing.invoice.state","Only DRAFT Supplier Invoice can be cancelled.");
                invoice.State=SupplierInvoiceState.Cancelled;invoice.Version++;invoice.CancelledAt=DateTimeOffset.UtcNow;
                return Result<PurchasingMutationReceipt>.Success(new(invoice.PublicId,"CANCELLED",invoice.Version,context.CorrelationId.Value));
            },ct);

    public async Task<Result<PurchaseMatchApprovalTarget>> GetMatchApprovalTargetAsync(
        Guid matchPublicId,IExecutionContext context,CancellationToken ct)
    {
        var match=await dbContext.Set<PurchaseMatchResultRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==matchPublicId,ct);
        if(match is null)return NotFound<PurchaseMatchApprovalTarget>("purchasing.match.not_found","Purchase match was not found.");
        if(match.State!=PurchaseMatchState.ExceptionRequired)
            return Business<PurchaseMatchApprovalTarget>("purchasing.match.approval.state","Only exception-required match can be approved.");
        var invoice=await dbContext.Set<SupplierInvoiceRecord>().AsNoTracking()
            .SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==match.SupplierInvoicePublicId,ct);
        var exception=await dbContext.Set<PurchaseMatchExceptionRecord>().AsNoTracking()
            .SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PurchaseMatchResultId==match.Id,ct);
        return Result<PurchaseMatchApprovalTarget>.Success(
            new(match.PublicId,invoice.PublicId,invoice.Version,exception.CreatorActorId));
    }

    public async Task<Result<PurchasingMutationReceipt>> CompleteMatchDecisionAsync(
        Guid matchPublicId,Guid approvalDecisionPublicId,ApprovalDecisionKind decision,
        IExecutionContext context,CancellationToken ct)
    {
        var match=await dbContext.Set<PurchaseMatchResultRecord>()
            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==matchPublicId,ct);
        if(match is null)return NotFound<PurchasingMutationReceipt>("purchasing.match.not_found","Purchase match was not found.");
        if(match.State!=PurchaseMatchState.ExceptionRequired)
            return Business<PurchasingMutationReceipt>("purchasing.match.approval.state","Purchase match no longer requires exception approval.");
        var exception=await dbContext.Set<PurchaseMatchExceptionRecord>()
            .SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PurchaseMatchResultId==match.Id,ct);
        var invoice=await dbContext.Set<SupplierInvoiceRecord>()
            .SingleAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==match.SupplierInvoicePublicId,ct);
        exception.ApprovalDecisionPublicId=approvalDecisionPublicId;
        exception.DecidedAt=DateTimeOffset.UtcNow;
        match.State=decision==ApprovalDecisionKind.Approved?PurchaseMatchState.ExceptionApproved:PurchaseMatchState.Blocked;
        await dbContext.SaveChangesAsync(ct);
        return Result<PurchasingMutationReceipt>.Success(new(
            invoice.PublicId,match.State==PurchaseMatchState.ExceptionApproved?"EXCEPTION_APPROVED":"BLOCKED",
            invoice.Version,context.CorrelationId.Value));
    }

    private async Task<Result<PurchasingMutationReceipt>> UpsertInvoiceDraftCoreAsync(
        Guid? invoicePublicId,long? expectedVersion,CreateSupplierInvoiceDraftCommand command,
        IExecutionContext context,CancellationToken innerCt)
    {
        if(command.CurrencyCode.Trim().ToUpperInvariant()!="TRY")
            return Business<PurchasingMutationReceipt>("purchasing.invoice.currency_policy",
                "Non-TRY authoritative minor-unit/FX policy is not available; Supplier Invoice final calculation fails closed.");
        if(command.DueDate<command.DocumentDate)
            return Validation<PurchasingMutationReceipt>("purchasing.invoice.dates","Due date cannot precede document date.");
        if(command.SourceMode==SupplierInvoiceSourceMode.Direct&&string.IsNullOrWhiteSpace(command.DirectReason))
            return Validation<PurchasingMutationReceipt>("purchasing.invoice.direct.reason","Direct Supplier Invoice requires a reason.");
        if(command.SourceMode!=SupplierInvoiceSourceMode.Direct&&!string.IsNullOrWhiteSpace(command.DirectReason))
            return Validation<PurchasingMutationReceipt>("purchasing.invoice.direct.reason_unexpected","Direct reason is valid only for DIRECT source mode.");

        var supplier=await ResolveSupplierAsync(context.CompanyId,command.SupplierPartyPublicId,innerCt);
        if(supplier is null)return Business<PurchasingMutationReceipt>("purchasing.supplier.not_eligible","Supplier must be ACTIVE with an ACTIVE Supplier role.");
        if(command.Lines.Count==0)return Validation<PurchasingMutationReceipt>("purchasing.invoice.lines","At least one line is required.");

        var lineInputs=command.Lines.Select(x=>new PurchasingTradeLineInput(
            x.Sequence,x.ProductPublicId,x.VariantPublicId,x.UomPublicId,x.Quantity,x.UnitPrice,x.LineDiscountPercent,x.TaxPercent)).ToArray();
        var resolved=await ResolveLinesAsync(context.CompanyId,lineInputs,innerCt);
        if(resolved.IsFailure)return Result<PurchasingMutationReceipt>.Failure(resolved.Error!);

        decimal priceVariance=0m;
        Guid? matchPoPublicId=null;
        Guid? matchReceiptPublicId=null;
        for(var i=0;i<command.Lines.Count;i++){
            var input=command.Lines[i];var trade=resolved.Value![i];
            if(command.SourceMode==SupplierInvoiceSourceMode.Direct){
                if(input.SourceDocumentPublicId.HasValue||input.SourceLinePublicId.HasValue||input.SourceVersion.HasValue||trade.Stockable)
                    return Business<PurchasingMutationReceipt>("purchasing.invoice.direct_stockable",
                        "Direct Supplier Invoice is financial-only and cannot source STOCKABLE Product.");
                continue;
            }

            if(!input.SourceDocumentPublicId.HasValue||!input.SourceLinePublicId.HasValue)
                return Business<PurchasingMutationReceipt>("purchasing.invoice.source","Sourced Supplier Invoice requires exact source document and line.");

            if(command.SourceMode==SupplierInvoiceSourceMode.PurchaseOrder){
                if(trade.Stockable)
                    return Business<PurchasingMutationReceipt>("purchasing.invoice.po_source","STOCKABLE Product requires POSTED Goods Receipt 3-way source.");
                var source=await (
                    from o in dbContext.Set<PurchaseOrderRecord>().AsNoTracking()
                    join v in dbContext.Set<PurchaseOrderVersionRecord>().AsNoTracking() on o.Id equals v.PurchaseOrderId
                    join l in dbContext.Set<PurchaseOrderLineRecord>().AsNoTracking() on v.Id equals l.PurchaseOrderVersionId
                    where o.CompanyId==context.CompanyId&&o.PublicId==input.SourceDocumentPublicId.Value&&
                          v.VersionNumber==(input.SourceVersion??o.CurrentVersionNumber)&&
                          l.LinePublicId==input.SourceLinePublicId.Value&&l.ProductId==trade.ProductId&&l.UomId==trade.UomId&&
                          (o.State==PurchaseOrderState.Confirmed||o.State==PurchaseOrderState.PartiallyReceived||
                           o.State==PurchaseOrderState.Completed||o.State==PurchaseOrderState.CancelledRemainder)
                    select new{o.PublicId,l.Quantity,l.UnitPrice}).SingleOrDefaultAsync(innerCt);
                if(source is null)return Business<PurchasingMutationReceipt>("purchasing.invoice.po_source","Purchase Order source line is not eligible.");
                if(input.Quantity>source.Quantity)
                    return Business<PurchasingMutationReceipt>("purchasing.invoice.over_invoice","Over-invoice is blocked without an authoritative tolerance policy.");
                matchPoPublicId=matchPoPublicId is null||matchPoPublicId==source.PublicId?source.PublicId:null;
                priceVariance+=input.UnitPrice-source.UnitPrice;
            }else{
                var source=await (
                    from gr in dbContext.Set<GoodsReceiptRecord>().AsNoTracking()
                    join gl in dbContext.Set<GoodsReceiptLineRecord>().AsNoTracking() on gr.Id equals gl.GoodsReceiptId
                    join po in dbContext.Set<PurchaseOrderRecord>().AsNoTracking() on gr.PurchaseOrderId equals po.Id
                    join pv in dbContext.Set<PurchaseOrderVersionRecord>().AsNoTracking()
                        on new{Id=po.Id,Version=gr.PurchaseOrderVersionNumber} equals new{Id=pv.PurchaseOrderId,Version=pv.VersionNumber}
                    join pl in dbContext.Set<PurchaseOrderLineRecord>().AsNoTracking()
                        on new{VersionId=pv.Id,Line=gl.PurchaseOrderLinePublicId} equals new{VersionId=pl.PurchaseOrderVersionId,Line=pl.LinePublicId}
                    where gr.CompanyId==context.CompanyId&&gr.PublicId==input.SourceDocumentPublicId.Value&&
                          gr.State==GoodsReceiptState.Posted&&gl.PublicId==input.SourceLinePublicId.Value&&
                          gl.ProductId==trade.ProductId&&gl.UomId==trade.UomId
                    select new{GoodsReceiptPublicId=gr.PublicId,PurchaseOrderPublicId=po.PublicId,gl.Quantity,pl.UnitPrice})
                    .SingleOrDefaultAsync(innerCt);
                if(source is null)return Business<PurchasingMutationReceipt>("purchasing.invoice.receipt_source","Goods Receipt source line must be POSTED and match Product/UOM.");
                if(input.Quantity>source.Quantity)
                    return Business<PurchasingMutationReceipt>("purchasing.invoice.over_invoice","Over-invoice is blocked without an authoritative tolerance policy.");
                matchReceiptPublicId=matchReceiptPublicId is null||matchReceiptPublicId==source.GoodsReceiptPublicId?source.GoodsReceiptPublicId:null;
                matchPoPublicId=matchPoPublicId is null||matchPoPublicId==source.PurchaseOrderPublicId?source.PurchaseOrderPublicId:null;
                priceVariance+=input.UnitPrice-source.UnitPrice;
            }
        }

        var calc=PurchaseCommercialCalculator.Calculate(
            lineInputs.Select(x=>new PurchaseCommercialLineInput(
                x.Sequence,x.Quantity,x.UnitPrice,x.LineDiscountPercent,x.TaxPercent)).ToArray(),
            command.DocumentDiscountPercent,2);

        var now=DateTimeOffset.UtcNow;
        SupplierInvoiceRecord invoice;
        if(invoicePublicId.HasValue){
            var existing=await dbContext.Set<SupplierInvoiceRecord>()
                .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==invoicePublicId.Value,innerCt);
            if(existing is null)return NotFound<PurchasingMutationReceipt>("purchasing.invoice.not_found","Supplier Invoice DRAFT was not found.");
            invoice=existing;
            if(!expectedVersion.HasValue||invoice.Version!=expectedVersion.Value)
                return Conflict<PurchasingMutationReceipt>("purchasing.invoice.stale","Supplier Invoice version is stale.",true);
            if(invoice.State!=SupplierInvoiceState.Draft)
                return Business<PurchasingMutationReceipt>("purchasing.invoice.state","Only DRAFT Supplier Invoice can be replaced.");
            var oldLines=await dbContext.Set<SupplierInvoiceLineRecord>()
                .Where(x=>x.CompanyId==context.CompanyId&&x.SupplierInvoiceId==invoice.Id).ToArrayAsync(innerCt);
            var oldLineIds=oldLines.Select(x=>x.Id).ToArray();
            dbContext.RemoveRange(dbContext.Set<SupplierInvoiceSourceLinkRecord>().Where(x=>x.CompanyId==context.CompanyId&&oldLineIds.Contains(x.SupplierInvoiceLineId)));
            dbContext.RemoveRange(oldLines);
            var oldMatches=await dbContext.Set<PurchaseMatchResultRecord>()
                .Where(x=>x.CompanyId==context.CompanyId&&x.SupplierInvoicePublicId==invoice.PublicId).ToArrayAsync(innerCt);
            var oldMatchIds=oldMatches.Select(x=>x.Id).ToArray();
            dbContext.RemoveRange(dbContext.Set<PurchaseMatchExceptionRecord>().Where(x=>x.CompanyId==context.CompanyId&&oldMatchIds.Contains(x.PurchaseMatchResultId)));
            dbContext.RemoveRange(oldMatches);
            invoice.Number=command.Number.Trim();invoice.SupplierPartyId=supplier.Id;invoice.SourceMode=command.SourceMode;
            invoice.DocumentDate=command.DocumentDate;invoice.DueDate=command.DueDate;invoice.CurrencyCode="TRY";
            invoice.DocumentDiscountPercent=command.DocumentDiscountPercent;invoice.SupplierCodeSnapshot=supplier.Code;
            invoice.SupplierLegalNameSnapshot=supplier.LegalName;invoice.NetTotal=calc.NetTotal;invoice.TaxTotal=calc.TaxTotal;
            invoice.GrossTotal=calc.GrossTotal;invoice.Version++;
        }else{
            invoice=new SupplierInvoiceRecord{
                PublicId=Guid.NewGuid(),CompanyId=context.CompanyId,Number=command.Number.Trim(),
                SupplierPartyId=supplier.Id,State=SupplierInvoiceState.Draft,SourceMode=command.SourceMode,
                DocumentDate=command.DocumentDate,DueDate=command.DueDate,CurrencyCode="TRY",
                DocumentDiscountPercent=command.DocumentDiscountPercent,SupplierCodeSnapshot=supplier.Code,
                SupplierLegalNameSnapshot=supplier.LegalName,NetTotal=calc.NetTotal,TaxTotal=calc.TaxTotal,
                GrossTotal=calc.GrossTotal,Version=1,CreatorActorId=context.ActorId,CreatedAt=now
            };
            dbContext.Add(invoice);
        }
        await dbContext.SaveChangesAsync(innerCt);

        for(var i=0;i<command.Lines.Count;i++){
            var input=command.Lines[i];var trade=resolved.Value![i];var lineCalc=calc.Lines.Single(x=>x.Sequence==input.Sequence);
            var line=new SupplierInvoiceLineRecord{
                PublicId=Guid.NewGuid(),SupplierInvoiceId=invoice.Id,CompanyId=context.CompanyId,
                Sequence=input.Sequence,ProductId=trade.ProductId,VariantId=trade.VariantId,UomId=trade.UomId,
                ConversionFactorSnapshot=trade.ConversionFactor,ProductCodeSnapshot=trade.ProductCode,
                ProductNameSnapshot=trade.ProductName,VariantCodeSnapshot=trade.VariantCode,VariantNameSnapshot=trade.VariantName,
                UomCodeSnapshot=trade.UomCode,UomNameSnapshot=trade.UomName,Quantity=input.Quantity,UnitPrice=input.UnitPrice,
                LineDiscountPercent=input.LineDiscountPercent,DocumentDiscount=lineCalc.DocumentDiscount,TaxPercent=input.TaxPercent,
                TaxableBase=lineCalc.TaxableBase,TaxAmount=lineCalc.Tax,LineTotal=lineCalc.LineTotal
            };
            dbContext.Add(line);await dbContext.SaveChangesAsync(innerCt);
            dbContext.Add(new SupplierInvoiceSourceLinkRecord{
                PublicId=Guid.NewGuid(),SupplierInvoiceLineId=line.Id,CompanyId=context.CompanyId,
                SourceMode=command.SourceMode,SourceDocumentPublicId=input.SourceDocumentPublicId,
                SourceLinePublicId=input.SourceLinePublicId,SourceVersion=input.SourceVersion,Quantity=input.Quantity
            });
        }

        var matchState=command.SourceMode==SupplierInvoiceSourceMode.Direct
            ? PurchaseMatchState.ExceptionRequired
            : priceVariance==0m?PurchaseMatchState.Matched:PurchaseMatchState.Blocked;
        var match=new PurchaseMatchResultRecord{
            PublicId=Guid.NewGuid(),CompanyId=context.CompanyId,
            Kind=command.SourceMode==SupplierInvoiceSourceMode.GoodsReceipt?PurchaseMatchKind.ThreeWay:PurchaseMatchKind.TwoWay,
            State=matchState,SupplierInvoicePublicId=invoice.PublicId,PurchaseOrderPublicId=matchPoPublicId,
            GoodsReceiptPublicId=matchReceiptPublicId,QuantityVariance=0m,PriceVariance=priceVariance,
            BlockReason=matchState==PurchaseMatchState.Blocked?"Zero-tolerance price variance.":null,
            EvaluatedAt=now,EvaluatedByActorId=context.ActorId
        };
        dbContext.Add(match);await dbContext.SaveChangesAsync(innerCt);
        if(command.SourceMode==SupplierInvoiceSourceMode.Direct){
            dbContext.Add(new PurchaseMatchExceptionRecord{
                PublicId=Guid.NewGuid(),CompanyId=context.CompanyId,PurchaseMatchResultId=match.Id,
                Reason=command.DirectReason!.Trim(),CreatorActorId=context.ActorId,CreatedAt=now
            });
        }

        return Result<PurchasingMutationReceipt>.Success(new(
            invoice.PublicId,"DRAFT",invoice.Version,context.CorrelationId.Value));
    }

    public async Task<Result<PurchaseMatchPreview>> PreviewMatchAsync(
        SupplierInvoiceSourceMode sourceMode,Guid sourceDocumentPublicId,IExecutionContext context,CancellationToken ct)
    {
        if(sourceMode==SupplierInvoiceSourceMode.GoodsReceipt){
            var receipt=await dbContext.Set<GoodsReceiptRecord>().AsNoTracking()
                .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==sourceDocumentPublicId,ct);
            if(receipt is null)return NotFound<PurchaseMatchPreview>("purchasing.match.receipt_not_found","Goods Receipt was not found.");
            if(receipt.State!=GoodsReceiptState.Posted)
                return Business<PurchaseMatchPreview>("purchasing.match.receipt_state","3-way source requires POSTED Goods Receipt.");
            var order=await dbContext.Set<PurchaseOrderRecord>().AsNoTracking()
                .SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==receipt.PurchaseOrderId,ct);
            return Result<PurchaseMatchPreview>.Success(new(
                PurchaseMatchKind.ThreeWay,PurchaseMatchState.Pending,order.PublicId,receipt.PublicId,0m,0m,null));
        }
        if(sourceMode==SupplierInvoiceSourceMode.PurchaseOrder){
            var order=await dbContext.Set<PurchaseOrderRecord>().AsNoTracking()
                .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==sourceDocumentPublicId,ct);
            if(order is null)return NotFound<PurchaseMatchPreview>("purchasing.match.order_not_found","Purchase Order was not found.");
            if(order.State is not (PurchaseOrderState.Confirmed or PurchaseOrderState.PartiallyReceived or PurchaseOrderState.Completed))
                return Business<PurchaseMatchPreview>("purchasing.match.order_state","2-way source requires confirmed Purchase Order.");
            return Result<PurchaseMatchPreview>.Success(new(
                PurchaseMatchKind.TwoWay,PurchaseMatchState.Pending,order.PublicId,null,0m,0m,null));
        }
        return Validation<PurchaseMatchPreview>("purchasing.match.direct","Direct Invoice has no Purchase match source.");
    }

    public async Task<Result<IReadOnlyList<PurchaseReturnSourceLineView>>> PreviewReturnSourceAsync(
        Guid goodsReceiptPublicId,IExecutionContext context,CancellationToken ct)
    {
        var receipt=await dbContext.Set<GoodsReceiptRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==goodsReceiptPublicId,ct);
        if(receipt is null)return NotFound<IReadOnlyList<PurchaseReturnSourceLineView>>("purchasing.return.receipt_not_found","Goods Receipt was not found.");
        if(receipt.State!=GoodsReceiptState.Posted)
            return Business<IReadOnlyList<PurchaseReturnSourceLineView>>("purchasing.return.receipt_state","Purchase Return source requires POSTED Goods Receipt.");
        var order=await dbContext.Set<PurchaseOrderRecord>().AsNoTracking()
            .SingleAsync(x=>x.CompanyId==context.CompanyId&&x.Id==receipt.PurchaseOrderId,ct);
        var lines=await dbContext.Set<GoodsReceiptLineRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&x.GoodsReceiptId==receipt.Id).OrderBy(x=>x.Sequence).ToArrayAsync(ct);
        var products=await dbContext.Set<ProductRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Select(l=>l.ProductId).Contains(x.Id)).ToDictionaryAsync(x=>x.Id,ct);
        var variants=await dbContext.Set<ProductVariantRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Where(l=>l.VariantId.HasValue).Select(l=>l.VariantId!.Value).Contains(x.Id))
            .ToDictionaryAsync(x=>x.Id,ct);
        var uoms=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Select(l=>l.UomId).Contains(x.Id)).ToDictionaryAsync(x=>x.Id,ct);
        var warehouses=await dbContext.Set<WarehouseRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Select(l=>l.WarehouseId).Contains(x.Id)).ToDictionaryAsync(x=>x.Id,ct);
        var locations=await dbContext.Set<LocationRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Where(l=>l.LocationId.HasValue).Select(l=>l.LocationId!.Value).Contains(x.Id))
            .ToDictionaryAsync(x=>x.Id,ct);
        var lots=await dbContext.Set<InventoryLotRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Where(l=>l.LotId.HasValue).Select(l=>l.LotId!.Value).Contains(x.Id))
            .ToDictionaryAsync(x=>x.Id,ct);
        var serials=await dbContext.Set<InventorySerialRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==context.CompanyId&&lines.Where(l=>l.SerialId.HasValue).Select(l=>l.SerialId!.Value).Contains(x.Id))
            .ToDictionaryAsync(x=>x.Id,ct);
        return Result<IReadOnlyList<PurchaseReturnSourceLineView>>.Success(
            lines.Select(x=>new PurchaseReturnSourceLineView(
                receipt.PublicId,x.PublicId,order.PublicId,x.PurchaseOrderLinePublicId,
                products[x.ProductId].PublicId,x.VariantId.HasValue?variants[x.VariantId.Value].PublicId:null,
                uoms[x.UomId].PublicId,x.Quantity,warehouses[x.WarehouseId].PublicId,
                x.LocationId.HasValue?locations[x.LocationId.Value].PublicId:null,
                x.LotId.HasValue?lots[x.LotId.Value].PublicId:null,
                x.SerialId.HasValue?serials[x.SerialId.Value].PublicId:null)).ToArray());
    }

    private async Task<ResolvedSupplier?> ResolveSupplierAsync(Guid companyId,Guid publicId,CancellationToken ct)
    {
        var party=await dbContext.Set<PartyRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==publicId&&x.State==PartyState.Active,ct);
        if(party is null)return null;
        var supplier=await dbContext.Set<PartyRoleRecord>().AsNoTracking()
            .AnyAsync(x=>x.PartyId==party.Id&&x.RoleType==PartyRoleType.Supplier&&x.State==PartyRoleState.Active,ct);
        return supplier?new ResolvedSupplier(party.Id,party.PartyCode,party.LegalName):null;
    }

    private async Task<Result<IReadOnlyList<ResolvedTrade>>> ResolveLinesAsync(
        Guid companyId,IReadOnlyList<PurchasingTradeLineInput> lines,CancellationToken ct)
    {
        if(lines.Count==0||lines.Select(x=>x.Sequence).Distinct().Count()!=lines.Count)
            return Validation<IReadOnlyList<ResolvedTrade>>("purchasing.lines.invalid","At least one line with unique sequence is required.");
        var result=new List<ResolvedTrade>();
        foreach(var line in lines){
            if(line.ProductPublicId==Guid.Empty||line.VariantPublicId==Guid.Empty||line.UomPublicId==Guid.Empty||
               line.Sequence<=0||line.Quantity<=0m||line.UnitPrice<0m||
               line.LineDiscountPercent<0m||line.LineDiscountPercent>100m||line.TaxPercent<0m||line.TaxPercent>100m)
                return Validation<IReadOnlyList<ResolvedTrade>>("purchasing.line.invalid","Purchase line identity, quantity, price, discount or tax is invalid.");
            var trade=await ResolveTradeAsync(companyId,line.ProductPublicId,line.VariantPublicId,line.UomPublicId,ct);
            if(trade.IsFailure)return Result<IReadOnlyList<ResolvedTrade>>.Failure(trade.Error!);
            result.Add(trade.Value!);
        }
        return Result<IReadOnlyList<ResolvedTrade>>.Success(result);
    }

    private async Task<Result<ResolvedTrade>> ResolveTradeAsync(
        Guid companyId,Guid productPublicId,Guid? variantPublicId,Guid uomPublicId,CancellationToken ct)
    {
        var product=await dbContext.Set<ProductRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==productPublicId,ct);
        if(product is null||product.State!=ProductState.Active||!product.Purchasable)
            return Business<ResolvedTrade>("purchasing.product.not_purchasable","Product must be ACTIVE and PURCHASABLE.");
        ProductVariantRecord? variant=null;
        if(variantPublicId.HasValue){
            variant=await dbContext.Set<ProductVariantRecord>().AsNoTracking()
                .SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==variantPublicId.Value&&x.ProductId==product.Id,ct);
            if(variant is null||variant.State!=ProductMasterRecordState.Active)
                return Business<ResolvedTrade>("purchasing.variant.not_active","Variant must belong to Product and be ACTIVE.");
        }
        var uom=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==uomPublicId&&x.State==ProductMasterRecordState.Active,ct);
        if(uom is null)return Business<ResolvedTrade>("purchasing.uom.not_active","UOM must be ACTIVE.");
        var productUom=await dbContext.Set<ProductUomRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&x.ProductId==product.Id&&x.UomId==uom.Id&&
                x.State==ProductMasterRecordState.Active&&x.VariantId==(variant==null?null:variant.Id))
            .OrderByDescending(x=>x.Id).FirstOrDefaultAsync(ct);
        if(productUom is null)return Business<ResolvedTrade>("purchasing.uom.not_allowed","Selected UOM is not active for Product/Variant.");
        return Result<ResolvedTrade>.Success(new(
            product.Id,variant?.Id,uom.Id,productUom.ConversionFactor,product.PublicId,variant?.PublicId,uom.PublicId,
            product.ProductCode,product.Name,variant?.VariantCode,variant?.Name,uom.Code,uom.Name,product.Stockable));
    }

    private static ApplicationError? ValidateDocument(
        string number,string currency,decimal documentDiscount,IReadOnlyList<PurchasingTradeLineInput> lines)
    {
        if(string.IsNullOrWhiteSpace(number)||number!=number.Trim())
            return new(ErrorCategory.Validation,"purchasing.number.invalid","Document number is required and must be trimmed.");
        var code=currency?.Trim().ToUpperInvariant();
        if(code is null||code.Length!=3||code.Any(ch=>ch<'A'||ch>'Z'))
            return new(ErrorCategory.Validation,"purchasing.currency.invalid","Currency must be a three-letter uppercase code.");
        if(documentDiscount<0m||documentDiscount>100m||lines.Count==0)
            return new(ErrorCategory.Validation,"purchasing.document.invalid","Document discount or lines are invalid.");
        return null;
    }

    private async Task<Dictionary<Guid,decimal>> NetReceivedAsync(
        Guid companyId,long orderId,Guid[] lineIds,CancellationToken ct)
    {
        if(lineIds.Length==0)return new();
        var rows=await (
            from l in dbContext.Set<GoodsReceiptLineRecord>().AsNoTracking()
            join r in dbContext.Set<GoodsReceiptRecord>().AsNoTracking() on l.GoodsReceiptId equals r.Id
            where l.CompanyId==companyId&&r.CompanyId==companyId&&r.PurchaseOrderId==orderId&&
                lineIds.Contains(l.PurchaseOrderLinePublicId)&&r.State==GoodsReceiptState.Posted
            group l by l.PurchaseOrderLinePublicId into g
            select new ValueTuple<Guid,decimal>(g.Key,g.Sum(x=>x.Quantity))).ToArrayAsync(ct);
        return rows.ToDictionary(x=>x.Item1,x=>x.Item2);
    }

    private async Task<Dictionary<Guid,decimal>> NetCommittedReceiptAsync(
        Guid companyId,long orderId,Guid[] lineIds,CancellationToken ct)
    {
        if(lineIds.Length==0)return new();
        var rows=await (
            from l in dbContext.Set<GoodsReceiptLineRecord>().AsNoTracking()
            join r in dbContext.Set<GoodsReceiptRecord>().AsNoTracking() on l.GoodsReceiptId equals r.Id
            where l.CompanyId==companyId&&r.CompanyId==companyId&&r.PurchaseOrderId==orderId&&
                lineIds.Contains(l.PurchaseOrderLinePublicId)&&
                (r.State==GoodsReceiptState.Draft||r.State==GoodsReceiptState.Ready||r.State==GoodsReceiptState.Posted)
            group l by l.PurchaseOrderLinePublicId into g
            select new ValueTuple<Guid,decimal>(g.Key,g.Sum(x=>x.Quantity))).ToArrayAsync(ct);
        return rows.ToDictionary(x=>x.Item1,x=>x.Item2);
    }

    private async Task<Result<PurchasingMutationReceipt>> MutateAsync(
        string scope,string operationKey,string action,string entityType,string resultCode,
        IExecutionContext context,Func<CancellationToken,Task<Result<PurchasingMutationReceipt>>> mutation,CancellationToken ct)
    {
        if(string.IsNullOrWhiteSpace(operationKey))
            return Validation<PurchasingMutationReceipt>("purchasing.operation_key.required","Idempotency operation key is required.");
        var owns=dbContext.Database.CurrentTransaction is null;
        IDbContextTransaction? tx=null;
        if(owns)tx=await dbContext.Database.BeginTransactionAsync(ct);
        try{
            var result=await mutation(ct);
            if(result.IsFailure){
                if(tx is not null)await tx.RollbackAsync(ct);
                dbContext.ChangeTracker.Clear();
                return result;
            }
            var now=DateTimeOffset.UtcNow;
            idempotencyStore.Add(new IdempotencyOperation(scope,operationKey,null,now));
            auditWriter.Append(new AuditEntry(
                context.ActorId,context.CompanyId,context.BranchId,context.CorrelationId.Value,
                "Purchasing",action,entityType,result.Value!.PublicId,null,now));
            outboxWriter.Enqueue(new OutboxMessage(
                Guid.NewGuid(),$"Purchasing.{action}","Purchasing",result.Value.PublicId,1,
                JsonSerializer.Serialize(new{entityType,entityPublicId=result.Value.PublicId,state=result.Value.State,version=result.Value.Version}),now,now));
            await dbContext.SaveChangesAsync(ct);
            if(!await idempotencyStore.MarkSucceededAsync(scope,operationKey,resultCode,now,ct))
                throw new InvalidOperationException("Purchasing idempotency state could not be completed.");
            if(tx is not null)await tx.CommitAsync(ct);
            return result;
        }catch(DbUpdateConcurrencyException){
            if(tx is not null)await tx.RollbackAsync(ct);dbContext.ChangeTracker.Clear();
            return Conflict<PurchasingMutationReceipt>("purchasing.concurrency.stale","Purchasing document was changed by another operation.",true);
        }catch(DbUpdateException ex) when(IsConstraint(ex,"ux_idempotency_scope_key")){
            if(tx is not null)await tx.RollbackAsync(ct);dbContext.ChangeTracker.Clear();
            return Conflict<PurchasingMutationReceipt>("purchasing.operation.duplicate","Operation key was already used.");
        }catch(DbUpdateException ex) when(IsUniqueViolation(ex)){
            if(tx is not null)await tx.RollbackAsync(ct);dbContext.ChangeTracker.Clear();
            return Conflict<PurchasingMutationReceipt>("purchasing.unique.conflict","Purchasing document or source identity conflicts with an existing record.");
        }finally{if(tx is not null)await tx.DisposeAsync();}
    }

    private static string? Normalize(string? value)=>string.IsNullOrWhiteSpace(value)?null:value.Trim();
    private static bool IsConstraint(DbUpdateException ex,string name)=>
        ex.InnerException is PostgresException pg&&pg.SqlState==PostgresErrorCodes.UniqueViolation&&
        string.Equals(pg.ConstraintName,name,StringComparison.Ordinal);
    private static bool IsUniqueViolation(DbUpdateException ex)=>
        ex.InnerException is PostgresException pg&&pg.SqlState==PostgresErrorCodes.UniqueViolation;

    private static Result<T> Validation<T>(string code,string message)=>Result<T>.Failure(new ApplicationError(ErrorCategory.Validation,code,message));
    private static Result<T> NotFound<T>(string code,string message)=>Result<T>.Failure(new ApplicationError(ErrorCategory.NotFound,code,message));
    private static Result<T> Business<T>(string code,string message)=>Result<T>.Failure(new ApplicationError(ErrorCategory.BusinessRule,code,message));
    private static Result<T> Conflict<T>(string code,string message,bool concurrency=false)=>
        Result<T>.Failure(new ApplicationError(concurrency?ErrorCategory.Concurrency:ErrorCategory.Conflict,code,message));

    private static string OrderStateCode(PurchaseOrderState s)=>s switch{
        PurchaseOrderState.Draft=>"DRAFT",PurchaseOrderState.PendingApproval=>"PENDING_APPROVAL",
        PurchaseOrderState.Confirmed=>"CONFIRMED",PurchaseOrderState.OnHold=>"ON_HOLD",
        PurchaseOrderState.PartiallyReceived=>"PARTIALLY_RECEIVED",PurchaseOrderState.Completed=>"COMPLETED",
        PurchaseOrderState.CancelledRemainder=>"CANCELLED_REMAINDER",_=>"CANCELLED"};
    private static string ReceiptStateCode(GoodsReceiptState s)=>s switch{
        GoodsReceiptState.Draft=>"DRAFT",GoodsReceiptState.Ready=>"READY",GoodsReceiptState.Posted=>"POSTED",
        GoodsReceiptState.Reversed=>"REVERSED",_=>"CANCELLED"};
    private static string InvoiceStateCode(SupplierInvoiceState s)=>s==SupplierInvoiceState.Draft?"DRAFT":"CANCELLED";
}

public sealed class EfPurchasingTransactionCoordinator(MarsDbContext dbContext):IPurchasingTransactionCoordinator
{
    public async Task<Result<T>> ExecuteAsync<T>(
        Func<CancellationToken,Task<Result<T>>> operation,CancellationToken cancellationToken)
    {
        if(dbContext.Database.CurrentTransaction is not null)return await operation(cancellationToken);
        await using var tx=await dbContext.Database.BeginTransactionAsync(cancellationToken);
        try{
            var result=await operation(cancellationToken);
            if(result.IsFailure){
                await tx.RollbackAsync(cancellationToken);
                dbContext.ChangeTracker.Clear();
                return result;
            }
            await tx.CommitAsync(cancellationToken);
            return result;
        }catch{
            await tx.RollbackAsync(cancellationToken);
            dbContext.ChangeTracker.Clear();
            throw;
        }
    }
}
