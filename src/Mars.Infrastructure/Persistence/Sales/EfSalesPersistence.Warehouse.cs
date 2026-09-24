using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Sales;
using Mars.Application.Warehouse;
using Mars.Domain.Inventory;
using Mars.Domain.Sales;
using Mars.Infrastructure.Persistence.Inventory;
using Microsoft.EntityFrameworkCore;

namespace Mars.Infrastructure.Persistence.Sales;

public sealed partial class EfSalesPersistence : ISalesWarehouseDispatchAuthority
{
    public async Task<Result<WarehouseMutationReceipt>> BindSourceAsync(
        BindDispatchSourceCommand command,
        IExecutionContext context,
        CancellationToken ct)
    {
        var result=await MutateAsync(
            "sales.dispatch.warehouse_bind",
            command.OperationKey,
            "DispatchWarehouseSourceBound",
            "Dispatch",
            "sales.dispatch.warehouse_bind.completed",
            context,
            async innerCt=>
            {
                var dispatch=await LockDispatchAsync(command.DispatchPublicId,context.CompanyId,innerCt);
                if(dispatch is null)
                    return NotFound<SalesMutationReceipt>("sales.dispatch.not_found","Dispatch was not found.");
                if(dispatch.Version!=command.ExpectedVersion)
                    return Conflict<SalesMutationReceipt>("sales.dispatch.stale","Dispatch version is stale.",true);
                if(dispatch.State is not (DispatchState.Draft or DispatchState.Ready))
                    return Business<SalesMutationReceipt>("sales.dispatch.warehouse_bind_state","Warehouse source can be bound only before Dispatch POST.");

                var warehouse=await dbContext.Set<WarehouseRecord>().AsNoTracking()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.Id==dispatch.WarehouseId&&x.PublicId==command.WarehousePublicId,innerCt);
                if(warehouse is null||warehouse.State!=InventoryMasterState.Active)
                    return Business<SalesMutationReceipt>("sales.dispatch.warehouse","Dispatch Warehouse must be ACTIVE.");

                var line=await dbContext.Set<DispatchLineRecord>()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.DispatchId==dispatch.Id&&x.PublicId==command.DispatchLinePublicId,innerCt);
                if(line is null)
                    return NotFound<SalesMutationReceipt>("sales.dispatch.line_not_found","Dispatch line was not found.");
                if(command.PickedQuantity<=0m)
                    return Business<SalesMutationReceipt>("sales.dispatch.pick_quantity","Picked quantity must be positive.");
                var allocated=await dbContext.Set<DispatchSourceAllocationRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==context.CompanyId&&x.DispatchLineId==line.Id)
                    .SumAsync(x=>(decimal?)x.Quantity,innerCt)??0m;
                if(allocated+command.PickedQuantity>line.Quantity)
                    return Business<SalesMutationReceipt>("sales.dispatch.pick_quantity","Cumulative picked source allocation cannot exceed Dispatch line quantity.");

                var location=await dbContext.Set<LocationRecord>().AsNoTracking()
                    .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.WarehouseId==dispatch.WarehouseId&&x.PublicId==command.LocationPublicId,innerCt);
                if(location is null||location.State!=InventoryMasterState.Active||!location.StockBearing)
                    return Business<SalesMutationReceipt>("sales.dispatch.location","Dispatch source Location must be ACTIVE and stock-bearing.");

                long? lotId=null;
                if(command.LotPublicId.HasValue)
                {
                    var lot=await dbContext.Set<InventoryLotRecord>().AsNoTracking()
                        .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==command.LotPublicId.Value&&x.ProductId==line.ProductId&&x.VariantId==line.VariantId,innerCt);
                    if(lot is null)
                        return Business<SalesMutationReceipt>("sales.dispatch.lot","Lot does not match Dispatch Product/Variant.");
                    lotId=lot.Id;
                }

                long? serialId=null;
                if(command.SerialPublicId.HasValue)
                {
                    var serial=await dbContext.Set<InventorySerialRecord>().AsNoTracking()
                        .SingleOrDefaultAsync(x=>x.CompanyId==context.CompanyId&&x.PublicId==command.SerialPublicId.Value&&x.ProductId==line.ProductId&&x.VariantId==line.VariantId,innerCt);
                    if(serial is null||serial.LotId!=lotId)
                        return Business<SalesMutationReceipt>("sales.dispatch.serial","Serial does not match Dispatch Product/Variant/Lot.");
                    if(command.PickedQuantity*line.ConversionFactorSnapshot!=1m)
                        return Business<SalesMutationReceipt>("sales.dispatch.serial_quantity","Serial source allocation must equal exactly one base unit.");
                    serialId=serial.Id;
                }

                dbContext.Add(new DispatchSourceAllocationRecord
                {
                    PublicId=Guid.NewGuid(),
                    DispatchLineId=line.Id,
                    CompanyId=context.CompanyId,
                    WarehouseId=warehouse.Id,
                    LocationId=location.Id,
                    LotId=lotId,
                    SerialId=serialId,
                    Quantity=command.PickedQuantity,
                    CreatorActorId=context.ActorId,
                    CreatedAt=DateTimeOffset.UtcNow
                });
                dispatch.Version++;

                return Result<SalesMutationReceipt>.Success(
                    new SalesMutationReceipt(dispatch.PublicId,DispatchStateCode(dispatch.State),dispatch.Version,context.CorrelationId.Value));
            },
            ct);

        return result.IsFailure
            ? Result<WarehouseMutationReceipt>.Failure(result.Error!)
            : Result<WarehouseMutationReceipt>.Success(
                new WarehouseMutationReceipt(
                    result.Value!.PublicId,
                    result.Value.State,
                    result.Value.Version,
                    result.Value.CorrelationId));
    }
}
