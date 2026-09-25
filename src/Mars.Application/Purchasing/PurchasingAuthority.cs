using Mars.Application.Finance;
using Mars.Application.Foundation.Approvals;
using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Inventory;
using Mars.Domain.Inventory;
using Mars.Domain.Purchasing;

namespace Mars.Application.Purchasing;

public sealed record PurchasingTradeLineInput(
    int Sequence,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal Quantity,
    decimal UnitPrice,
    decimal LineDiscountPercent,
    decimal TaxPercent);

public sealed record CreatePurchaseOrderCommand(
    string Number,
    Guid SupplierPartyPublicId,
    string CurrencyCode,
    string? PaymentTerms,
    decimal DocumentDiscountPercent,
    IReadOnlyList<PurchasingTradeLineInput> Lines,
    string OperationKey);

public sealed record PurchaseOrderAmendmentDeltaInput(
    Guid PurchaseOrderLinePublicId,
    decimal QuantityDelta);

public sealed record AmendPurchaseOrderCommand(
    Guid PurchaseOrderPublicId,
    long ExpectedVersion,
    string Reason,
    IReadOnlyList<PurchaseOrderAmendmentDeltaInput> Deltas,
    string OperationKey);

public sealed record PurchasingMutationReceipt(
    Guid PublicId,
    string State,
    long Version,
    string CorrelationId);

public sealed record PurchasingDocumentListItem(
    Guid PublicId,
    string Number,
    string State,
    string SupplierCode,
    string SupplierName,
    long Version,
    DateTimeOffset CreatedAt);

public sealed record PurchasingDocumentLineView(
    Guid PublicId,
    int Sequence,
    string ProductCode,
    string ProductName,
    string UomCode,
    decimal Quantity,
    decimal ProcessedQuantity,
    decimal RemainderQuantity,
    decimal UnitPrice,
    decimal LineDiscountPercent,
    decimal TaxPercent);

public sealed record PurchasingDocumentDetailView(
    Guid PublicId,
    string Number,
    string State,
    string SupplierCode,
    string SupplierName,
    string CurrencyCode,
    string? PaymentTerms,
    long Version,
    long EffectiveVersion,
    IReadOnlyList<PurchasingDocumentLineView> Lines);

public sealed record CreateGoodsReceiptLineInput(
    int Sequence,
    Guid PurchaseOrderLinePublicId,
    decimal Quantity,
    Guid? LocationPublicId,
    Guid? LotPublicId,
    Guid? SerialPublicId);

public sealed record CreateGoodsReceiptCommand(
    string Number,
    Guid PurchaseOrderPublicId,
    long PurchaseOrderVersion,
    Guid WarehousePublicId,
    IReadOnlyList<CreateGoodsReceiptLineInput> Lines,
    string OperationKey);

public sealed record GoodsReceiptPostLinePlan(
    Guid GoodsReceiptLinePublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal Quantity,
    decimal ConversionFactorSnapshot,
    Guid WarehousePublicId,
    Guid? LocationPublicId,
    Guid? LotPublicId,
    Guid? SerialPublicId,
    decimal ProvisionalBaseValue,
    bool Stockable);

public sealed record GoodsReceiptPostPlan(
    Guid GoodsReceiptPublicId,
    Guid WarehousePublicId,
    IReadOnlyList<GoodsReceiptPostLinePlan> Lines);

public sealed record GoodsReceiptReverseLinePlan(
    Guid GoodsReceiptLinePublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal Quantity,
    decimal ConversionFactorSnapshot,
    Guid WarehousePublicId,
    Guid? LocationPublicId,
    Guid? LotPublicId,
    Guid? SerialPublicId,
    Guid OriginalMovementPublicId,
    bool Stockable);

public sealed record GoodsReceiptReversePlan(
    Guid GoodsReceiptPublicId,
    Guid WarehousePublicId,
    IReadOnlyList<GoodsReceiptReverseLinePlan> Lines);

public sealed record GoodsReceiptInventoryEffect(
    Guid GoodsReceiptLinePublicId,
    Guid MovementPublicId);

public sealed record CreateSupplierInvoiceDraftLineInput(
    int Sequence,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal Quantity,
    decimal UnitPrice,
    decimal LineDiscountPercent,
    decimal TaxPercent,
    Guid? SourceDocumentPublicId,
    Guid? SourceLinePublicId,
    long? SourceVersion);

public sealed record CreateSupplierInvoiceDraftCommand(
    string Number,
    Guid SupplierPartyPublicId,
    SupplierInvoiceSourceMode SourceMode,
    DateOnly DocumentDate,
    DateOnly DueDate,
    string CurrencyCode,
    decimal DocumentDiscountPercent,
    IReadOnlyList<CreateSupplierInvoiceDraftLineInput> Lines,
    string? DirectReason,
    string OperationKey);

public sealed record PurchaseMatchApprovalTarget(
    Guid MatchPublicId,
    Guid InvoicePublicId,
    long SnapshotVersion,
    Guid CreatorActorId);

public sealed record PurchaseReturnSourceLineView(
    Guid GoodsReceiptPublicId,
    Guid GoodsReceiptLinePublicId,
    Guid PurchaseOrderPublicId,
    Guid PurchaseOrderLinePublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal EligibleQuantity,
    Guid WarehousePublicId,
    Guid? LocationPublicId,
    Guid? LotPublicId,
    Guid? SerialPublicId);

public sealed record PurchaseMatchListItem(
    Guid PublicId,
    string Kind,
    string State,
    Guid SupplierInvoicePublicId,
    Guid? PurchaseOrderPublicId,
    Guid? GoodsReceiptPublicId,
    decimal QuantityVariance,
    decimal PriceVariance,
    string? Reason);

public sealed record PurchaseMatchPreview(
    PurchaseMatchKind Kind,
    PurchaseMatchState State,
    Guid? PurchaseOrderPublicId,
    Guid? GoodsReceiptPublicId,
    decimal QuantityVariance,
    decimal PriceVariance,
    string? BlockReason);

public interface IPurchasingPersistence
{
    Task<IReadOnlyList<PurchasingDocumentListItem>> ListOrdersAsync(Guid companyId, CancellationToken ct);
    Task<PurchasingDocumentDetailView?> GetOrderAsync(Guid companyId, Guid publicId, CancellationToken ct);
    Task<IReadOnlyList<PurchasingDocumentListItem>> ListReceiptsAsync(Guid companyId, CancellationToken ct);
    Task<PurchasingDocumentDetailView?> GetReceiptAsync(Guid companyId, Guid publicId, CancellationToken ct);
    Task<IReadOnlyList<PurchasingDocumentListItem>> ListInvoicesAsync(Guid companyId, CancellationToken ct);
    Task<IReadOnlyList<PurchaseMatchListItem>> ListMatchesAsync(Guid companyId, CancellationToken ct);

    Task<Result<PurchasingMutationReceipt>> CreateOrderAsync(
        CreatePurchaseOrderCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<PurchasingMutationReceipt>> ConfirmOrderAsync(
        Guid orderPublicId, long expectedVersion, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<PurchasingMutationReceipt>> AmendOrderAsync(
        AmendPurchaseOrderCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<PurchasingMutationReceipt>> CancelOrderRemainderAsync(
        Guid orderPublicId, long expectedVersion, string reason, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<PurchasingMutationReceipt>> CloseOrderAsync(
        Guid orderPublicId, long expectedVersion, string operationKey, IExecutionContext context, CancellationToken ct);

    Task<Result<PurchasingMutationReceipt>> CreateReceiptAsync(
        CreateGoodsReceiptCommand command, IExecutionContext context, CancellationToken ct);
    Task<Guid?> GetReceiptWarehousePublicIdAsync(Guid companyId, Guid receiptPublicId, CancellationToken ct);
    Task<Result<PurchasingMutationReceipt>> ReadyReceiptAsync(
        Guid receiptPublicId, long expectedVersion, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<PurchasingMutationReceipt>> CancelReceiptAsync(
        Guid receiptPublicId, long expectedVersion, string reason, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<GoodsReceiptPostPlan>> PrepareReceiptPostAsync(
        Guid receiptPublicId, IExecutionContext context, CancellationToken ct);
    Task<Result<PurchasingMutationReceipt>> CompleteReceiptPostAsync(
        Guid receiptPublicId,
        IReadOnlyList<GoodsReceiptInventoryEffect> effects,
        string operationKey,
        IExecutionContext context,
        CancellationToken ct);
    Task<Result<GoodsReceiptReversePlan>> PrepareReceiptReverseAsync(
        Guid receiptPublicId, IExecutionContext context, CancellationToken ct);
    Task<Result<PurchasingMutationReceipt>> CompleteReceiptReverseAsync(
        Guid receiptPublicId,
        IReadOnlyList<GoodsReceiptInventoryEffect> effects,
        string operationKey,
        IExecutionContext context,
        CancellationToken ct);

    Task<Result<PurchasingMutationReceipt>> CreateInvoiceDraftAsync(
        CreateSupplierInvoiceDraftCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<PurchasingMutationReceipt>> ReplaceInvoiceDraftAsync(
        Guid invoicePublicId, long expectedVersion, CreateSupplierInvoiceDraftCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<PurchasingMutationReceipt>> CancelInvoiceDraftAsync(
        Guid invoicePublicId, long expectedVersion, string reason, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<PurchaseMatchApprovalTarget>> GetMatchApprovalTargetAsync(
        Guid matchPublicId, IExecutionContext context, CancellationToken ct);
    Task<Result<PurchasingMutationReceipt>> CompleteMatchDecisionAsync(
        Guid matchPublicId, Guid approvalDecisionPublicId, ApprovalDecisionKind decision, IExecutionContext context, CancellationToken ct);
    Task<Result<PurchaseMatchPreview>> PreviewMatchAsync(
        SupplierInvoiceSourceMode sourceMode,
        Guid sourceDocumentPublicId,
        IExecutionContext context,
        CancellationToken ct);
    Task<Result<IReadOnlyList<PurchaseReturnSourceLineView>>> PreviewReturnSourceAsync(
        Guid goodsReceiptPublicId, IExecutionContext context, CancellationToken ct);
}

public interface IPurchasingTransactionCoordinator
{
    Task<Result<T>> ExecuteAsync<T>(
        Func<CancellationToken, Task<Result<T>>> operation,
        CancellationToken cancellationToken);
}

public sealed class PurchasingQueryHandler(
    IPermissionEvaluator permissions,
    IPurchasingPersistence persistence)
{
    public async Task<Result<IReadOnlyList<PurchasingDocumentListItem>>> ListOrdersAsync(
        IExecutionContext context, CancellationToken ct) =>
        await ReadAsync(PurchasingPermissions.OrderRead, () => persistence.ListOrdersAsync(context.CompanyId, ct), context, ct);

    public async Task<Result<PurchasingDocumentDetailView?>> GetOrderAsync(
        Guid id, IExecutionContext context, CancellationToken ct)
    {
        if (!await Granted(PurchasingPermissions.OrderRead, context, ct))
            return Result<PurchasingDocumentDetailView?>.Failure(Denied());
        return Result<PurchasingDocumentDetailView?>.Success(await persistence.GetOrderAsync(context.CompanyId, id, ct));
    }

    public async Task<Result<IReadOnlyList<PurchasingDocumentListItem>>> ListReceiptsAsync(
        IExecutionContext context, CancellationToken ct) =>
        await ReadAsync(PurchasingPermissions.ReceiptRead, () => persistence.ListReceiptsAsync(context.CompanyId, ct), context, ct);

    public async Task<Result<IReadOnlyList<PurchasingDocumentListItem>>> ListInvoicesAsync(
        IExecutionContext context, CancellationToken ct) =>
        await ReadAsync(PurchasingPermissions.InvoiceRead, () => persistence.ListInvoicesAsync(context.CompanyId, ct), context, ct);

    public async Task<Result<IReadOnlyList<PurchaseMatchListItem>>> ListMatchesAsync(
        IExecutionContext context, CancellationToken ct)
    {
        if (!await Granted(PurchasingPermissions.MatchRead, context, ct))
            return Result<IReadOnlyList<PurchaseMatchListItem>>.Failure(Denied());
        return Result<IReadOnlyList<PurchaseMatchListItem>>.Success(
            await persistence.ListMatchesAsync(context.CompanyId, ct));
    }

    public async Task<Result<IReadOnlyList<PurchaseReturnSourceLineView>>> PreviewReturnSourceAsync(
        Guid goodsReceiptPublicId,
        IExecutionContext context,
        CancellationToken ct)
    {
        if (!await Granted(PurchasingPermissions.ReturnRead, context, ct))
            return Result<IReadOnlyList<PurchaseReturnSourceLineView>>.Failure(Denied());
        if (goodsReceiptPublicId == Guid.Empty)
            return Result<IReadOnlyList<PurchaseReturnSourceLineView>>.Failure(
                new ApplicationError(ErrorCategory.Validation, "purchasing.return.source.invalid", "Goods Receipt id is required."));
        return await persistence.PreviewReturnSourceAsync(goodsReceiptPublicId, context, ct);
    }

    public async Task<Result<PurchaseMatchPreview>> PreviewMatchAsync(
        SupplierInvoiceSourceMode mode,
        Guid sourceDocumentPublicId,
        IExecutionContext context,
        CancellationToken ct)
    {
        if (!await Granted(PurchasingPermissions.MatchRead, context, ct))
            return Result<PurchaseMatchPreview>.Failure(Denied());
        if (mode == SupplierInvoiceSourceMode.Direct || sourceDocumentPublicId == Guid.Empty)
            return Result<PurchaseMatchPreview>.Failure(
                new ApplicationError(ErrorCategory.Validation, "purchasing.match.source.invalid",
                    "Purchase Order or Goods Receipt source identity is required."));
        return await persistence.PreviewMatchAsync(mode, sourceDocumentPublicId, context, ct);
    }

    private async Task<Result<IReadOnlyList<PurchasingDocumentListItem>>> ReadAsync(
        string permission,
        Func<Task<IReadOnlyList<PurchasingDocumentListItem>>> read,
        IExecutionContext context,
        CancellationToken ct)
    {
        if (!await Granted(permission, context, ct))
            return Result<IReadOnlyList<PurchasingDocumentListItem>>.Failure(Denied());
        return Result<IReadOnlyList<PurchasingDocumentListItem>>.Success(await read());
    }

    private Task<bool> Granted(string permission, IExecutionContext context, CancellationToken ct) =>
        permissions.IsGrantedAsync(context.ActorId, context.CompanyId, permission, ct);

    private static ApplicationError Denied() =>
        new(ErrorCategory.Authorization, "purchasing.permission.denied", "The required Purchasing permission is not granted.");
}

public sealed class PurchasingCommandHandler(
    IPermissionEvaluator permissions,
    IPurchasingPersistence persistence,
    IApprovalDecisionAuthority approvals,
    IInventoryPhysicalAuthority inventory,
    IFinanceValuationAuthority finance,
    IWarehouseAccessEvaluator warehouseAccess,
    IPurchasingTransactionCoordinator transactions)
{
    public Task<Result<PurchasingMutationReceipt>> CreateOrderAsync(
        CreatePurchaseOrderCommand command, IExecutionContext context, CancellationToken ct) =>
        WithPermission(PurchasingPermissions.OrderCreate, () => persistence.CreateOrderAsync(command, context, ct), context, ct);

    public Task<Result<PurchasingMutationReceipt>> ConfirmOrderAsync(
        Guid id, long version, string key, IExecutionContext context, CancellationToken ct) =>
        WithPermission(PurchasingPermissions.OrderConfirm, () => persistence.ConfirmOrderAsync(id, version, key, context, ct), context, ct);

    public Task<Result<PurchasingMutationReceipt>> AmendOrderAsync(
        AmendPurchaseOrderCommand command, IExecutionContext context, CancellationToken ct) =>
        WithPermission(PurchasingPermissions.OrderAmend, () => persistence.AmendOrderAsync(command, context, ct), context, ct);

    public Task<Result<PurchasingMutationReceipt>> CancelOrderRemainderAsync(
        Guid id, long version, string reason, string key, IExecutionContext context, CancellationToken ct) =>
        WithPermission(PurchasingPermissions.OrderCancelRemaining,
            () => persistence.CancelOrderRemainderAsync(id, version, reason, key, context, ct), context, ct);

    public Task<Result<PurchasingMutationReceipt>> CloseOrderAsync(
        Guid id, long version, string key, IExecutionContext context, CancellationToken ct) =>
        WithPermission(PurchasingPermissions.OrderClose, () => persistence.CloseOrderAsync(id, version, key, context, ct), context, ct);

    public async Task<Result<PurchasingMutationReceipt>> CreateReceiptAsync(
        CreateGoodsReceiptCommand command, IExecutionContext context, CancellationToken ct)
    {
        if (!await Granted(PurchasingPermissions.ReceiptCreate, context, ct))
            return Denied<PurchasingMutationReceipt>();
        if (!await warehouseAccess.IsGrantedAsync(context.ActorId, context.CompanyId, command.WarehousePublicId, ct))
            return WarehouseDenied<PurchasingMutationReceipt>();
        return await persistence.CreateReceiptAsync(command, context, ct);
    }

    public async Task<Result<PurchasingMutationReceipt>> ReadyReceiptAsync(
        Guid id, long version, string key, IExecutionContext context, CancellationToken ct)
    {
        if (!await Granted(PurchasingPermissions.ReceiptEditDraft, context, ct))
            return Denied<PurchasingMutationReceipt>();
        var warehouse = await persistence.GetReceiptWarehousePublicIdAsync(context.CompanyId, id, ct);
        if (!warehouse.HasValue)
            return Result<PurchasingMutationReceipt>.Failure(
                new ApplicationError(ErrorCategory.NotFound, "purchasing.receipt.not_found", "Goods Receipt was not found."));
        if (!await warehouseAccess.IsGrantedAsync(context.ActorId, context.CompanyId, warehouse.Value, ct))
            return WarehouseDenied<PurchasingMutationReceipt>();
        return await persistence.ReadyReceiptAsync(id, version, key, context, ct);
    }

    public Task<Result<PurchasingMutationReceipt>> CancelReceiptAsync(
        Guid id,long version,string reason,string key,IExecutionContext context,CancellationToken ct) =>
        WithPermission(PurchasingPermissions.ReceiptEditDraft,
            () => persistence.CancelReceiptAsync(id,version,reason,key,context,ct),context,ct);

    public async Task<Result<PurchasingMutationReceipt>> PostReceiptAsync(
        Guid receiptPublicId,
        string operationKey,
        IExecutionContext context,
        CancellationToken ct)
    {
        if (!await Granted(PurchasingPermissions.ReceiptPost, context, ct))
            return Denied<PurchasingMutationReceipt>();
        var warehouse = await persistence.GetReceiptWarehousePublicIdAsync(context.CompanyId, receiptPublicId, ct);
        if (!warehouse.HasValue)
            return Result<PurchasingMutationReceipt>.Failure(
                new ApplicationError(ErrorCategory.NotFound, "purchasing.receipt.not_found", "Goods Receipt was not found."));
        if (!await warehouseAccess.IsGrantedAsync(context.ActorId, context.CompanyId, warehouse.Value, ct))
            return WarehouseDenied<PurchasingMutationReceipt>();

        return await transactions.ExecuteAsync(async innerCt =>
        {
            var plan = await persistence.PrepareReceiptPostAsync(receiptPublicId, context, innerCt);
            if (plan.IsFailure) return Result<PurchasingMutationReceipt>.Failure(plan.Error!);

            var effects = new List<GoodsReceiptInventoryEffect>();
            var index = 0;
            foreach (var line in plan.Value!.Lines.Where(x => x.Stockable))
            {
                var target = InventoryPosition.Create(
                    line.WarehousePublicId,
                    line.LocationPublicId,
                    InventoryDispositionCode.Quarantine,
                    line.LotPublicId,
                    line.SerialPublicId);
                var source = InventorySourceIdentity.Create(
                    "Purchasing",
                    "GoodsReceipt",
                    plan.Value.GoodsReceiptPublicId,
                    line.GoodsReceiptLinePublicId);
                var movement = await inventory.PostAsync(
                    new InventoryMovementCommand(
                        line.ProductPublicId,
                        line.VariantPublicId,
                        line.UomPublicId,
                        line.Quantity,
                        line.ConversionFactorSnapshot,
                        null,
                        target,
                        source,
                        null,
                        operationKey + ":inventory:" + index++),
                    context,
                    innerCt);
                if (movement.IsFailure)
                    return Result<PurchasingMutationReceipt>.Failure(movement.Error!);
                effects.Add(new GoodsReceiptInventoryEffect(
                    line.GoodsReceiptLinePublicId,
                    movement.Value!.MovementPublicId));
            }

            var valuation = await finance.PostGoodsReceiptAsync(
                new FinanceGoodsReceiptValuationCommand(
                    plan.Value.GoodsReceiptPublicId,
                    DateOnly.FromDateTime(DateTime.UtcNow),
                    plan.Value.Lines.Where(x=>x.Stockable).Select(line=>{
                        var effect=effects.Single(x=>x.GoodsReceiptLinePublicId==line.GoodsReceiptLinePublicId);
                        return new FinanceReceiptValuationLine(
                            line.GoodsReceiptLinePublicId,effect.MovementPublicId,line.ProductPublicId,line.VariantPublicId,
                            line.UomPublicId,line.Quantity*line.ConversionFactorSnapshot,line.ProvisionalBaseValue);
                    }).ToArray(),
                    operationKey + ":finance"),
                context,
                innerCt);
            if(valuation.IsFailure)
                return Result<PurchasingMutationReceipt>.Failure(valuation.Error!);

            return await persistence.CompleteReceiptPostAsync(
                receiptPublicId,
                effects,
                operationKey + ":complete",
                context,
                innerCt);
        }, ct);
    }

    public async Task<Result<PurchasingMutationReceipt>> ReverseReceiptAsync(
        Guid receiptPublicId,
        string operationKey,
        IExecutionContext context,
        CancellationToken ct)
    {
        if (!await Granted(PurchasingPermissions.ReceiptReverse, context, ct))
            return Denied<PurchasingMutationReceipt>();
        var warehouse = await persistence.GetReceiptWarehousePublicIdAsync(context.CompanyId, receiptPublicId, ct);
        if (!warehouse.HasValue)
            return Result<PurchasingMutationReceipt>.Failure(
                new ApplicationError(ErrorCategory.NotFound, "purchasing.receipt.not_found", "Goods Receipt was not found."));
        if (!await warehouseAccess.IsGrantedAsync(context.ActorId, context.CompanyId, warehouse.Value, ct))
            return WarehouseDenied<PurchasingMutationReceipt>();

        return await transactions.ExecuteAsync(async innerCt =>
        {
            var plan = await persistence.PrepareReceiptReverseAsync(receiptPublicId, context, innerCt);
            if (plan.IsFailure) return Result<PurchasingMutationReceipt>.Failure(plan.Error!);

            var effects = new List<GoodsReceiptInventoryEffect>();
            var index = 0;
            foreach (var line in plan.Value!.Lines.Where(x => x.Stockable))
            {
                var sourcePosition = InventoryPosition.Create(
                    line.WarehousePublicId,
                    line.LocationPublicId,
                    InventoryDispositionCode.Quarantine,
                    line.LotPublicId,
                    line.SerialPublicId);
                var source = InventorySourceIdentity.Create(
                    "Purchasing",
                    "GoodsReceiptReversal",
                    plan.Value.GoodsReceiptPublicId,
                    line.GoodsReceiptLinePublicId);
                var movement = await inventory.PostAsync(
                    new InventoryMovementCommand(
                        line.ProductPublicId,
                        line.VariantPublicId,
                        line.UomPublicId,
                        line.Quantity,
                        line.ConversionFactorSnapshot,
                        sourcePosition,
                        null,
                        source,
                        line.OriginalMovementPublicId,
                        operationKey + ":inventory-reverse:" + index++),
                    context,
                    innerCt);
                if (movement.IsFailure)
                    return Result<PurchasingMutationReceipt>.Failure(movement.Error!);
                effects.Add(new GoodsReceiptInventoryEffect(line.GoodsReceiptLinePublicId, movement.Value!.MovementPublicId));
            }

            var valuation = await finance.ReverseGoodsReceiptAsync(
                plan.Value.GoodsReceiptPublicId,
                DateOnly.FromDateTime(DateTime.UtcNow),
                operationKey + ":finance",
                context,
                innerCt);
            if(valuation.IsFailure)
                return Result<PurchasingMutationReceipt>.Failure(valuation.Error!);

            return await persistence.CompleteReceiptReverseAsync(
                receiptPublicId, effects, operationKey + ":complete", context, innerCt);
        }, ct);
    }

    public async Task<Result<PurchasingMutationReceipt>> CreateInvoiceDraftAsync(
        CreateSupplierInvoiceDraftCommand command, IExecutionContext context, CancellationToken ct)
    {
        if (!await Granted(PurchasingPermissions.InvoiceCreate, context, ct))
            return Denied<PurchasingMutationReceipt>();
        if (command.SourceMode == SupplierInvoiceSourceMode.Direct &&
            !await Granted(PurchasingPermissions.InvoiceDirectCreate, context, ct))
            return Denied<PurchasingMutationReceipt>();
        return await persistence.CreateInvoiceDraftAsync(command, context, ct);
    }

    public async Task<Result<PurchasingMutationReceipt>> ReplaceInvoiceDraftAsync(
        Guid id,long version,CreateSupplierInvoiceDraftCommand command,IExecutionContext context,CancellationToken ct)
    {
        if (!await Granted(PurchasingPermissions.InvoiceEditDraft, context, ct))
            return Denied<PurchasingMutationReceipt>();
        if (command.SourceMode == SupplierInvoiceSourceMode.Direct &&
            !await Granted(PurchasingPermissions.InvoiceDirectCreate, context, ct))
            return Denied<PurchasingMutationReceipt>();
        return await persistence.ReplaceInvoiceDraftAsync(id,version,command,context,ct);
    }

    public Task<Result<PurchasingMutationReceipt>> CancelInvoiceDraftAsync(
        Guid id,long version,string reason,string key,IExecutionContext context,CancellationToken ct) =>
        WithPermission(PurchasingPermissions.InvoiceEditDraft,
            () => persistence.CancelInvoiceDraftAsync(id,version,reason,key,context,ct),context,ct);

    public async Task<Result<ApprovalDecisionReceipt>> ApproveMatchExceptionAsync(
        Guid matchPublicId,
        ApprovalDecisionKind decision,
        string? reason,
        string operationKey,
        IExecutionContext context,
        CancellationToken ct)
    {
        if (!await Granted(PurchasingPermissions.MatchApproveException, context, ct))
            return Result<ApprovalDecisionReceipt>.Failure(
                new ApplicationError(ErrorCategory.Authorization,"purchasing.permission.denied","The required Purchasing permission is not granted."));

        return await transactions.ExecuteAsync(async innerCt =>
        {
            var target = await persistence.GetMatchApprovalTargetAsync(matchPublicId, context, innerCt);
            if (target.IsFailure)
                return Result<ApprovalDecisionReceipt>.Failure(target.Error!);

            var approval = await approvals.DecideAsync(
                new ApprovalDecisionCommand(
                    "Purchasing","PurchaseMatchException",target.Value!.MatchPublicId,
                    target.Value.SnapshotVersion,target.Value.CreatorActorId,decision,reason,operationKey),
                context,innerCt);
            if (approval.IsFailure)
                return approval;

            var completed = await persistence.CompleteMatchDecisionAsync(
                matchPublicId,approval.Value!.PublicId,decision,context,innerCt);
            return completed.IsFailure
                ? Result<ApprovalDecisionReceipt>.Failure(completed.Error!)
                : approval;
        }, ct);
    }

    private async Task<Result<PurchasingMutationReceipt>> WithPermission(
        string permission,
        Func<Task<Result<PurchasingMutationReceipt>>> operation,
        IExecutionContext context,
        CancellationToken ct)
    {
        if (!await Granted(permission, context, ct))
            return Denied<PurchasingMutationReceipt>();
        return await operation();
    }

    private Task<bool> Granted(string permission, IExecutionContext context, CancellationToken ct) =>
        permissions.IsGrantedAsync(context.ActorId, context.CompanyId, permission, ct);

    private static Result<T> Denied<T>() =>
        Result<T>.Failure(new ApplicationError(
            ErrorCategory.Authorization,
            "purchasing.permission.denied",
            "The required Purchasing permission is not granted."));

    private static Result<T> WarehouseDenied<T>() =>
        Result<T>.Failure(new ApplicationError(
            ErrorCategory.Authorization,
            "purchasing.warehouse.scope_denied",
            "The current actor is not authorized for this Warehouse."));
}
