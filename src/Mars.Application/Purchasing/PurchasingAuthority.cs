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
    Guid? SerialPublicId);

public sealed record GoodsReceiptPostPlan(
    Guid GoodsReceiptPublicId,
    IReadOnlyList<GoodsReceiptPostLinePlan> Lines);

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
    string OperationKey);

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

    Task<Result<PurchasingMutationReceipt>> CreateOrderAsync(
        CreatePurchaseOrderCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<PurchasingMutationReceipt>> ConfirmOrderAsync(
        Guid orderPublicId, long expectedVersion, string operationKey, IExecutionContext context, CancellationToken ct);

    Task<Result<PurchasingMutationReceipt>> CreateReceiptAsync(
        CreateGoodsReceiptCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<GoodsReceiptPostPlan>> PrepareReceiptPostAsync(
        Guid receiptPublicId, IExecutionContext context, CancellationToken ct);
    Task<Result<PurchasingMutationReceipt>> CompleteReceiptPostAsync(
        Guid receiptPublicId,
        IReadOnlyList<GoodsReceiptInventoryEffect> effects,
        string operationKey,
        IExecutionContext context,
        CancellationToken ct);

    Task<Result<PurchasingMutationReceipt>> CreateInvoiceDraftAsync(
        CreateSupplierInvoiceDraftCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<PurchaseMatchPreview>> PreviewMatchAsync(
        SupplierInvoiceSourceMode sourceMode,
        Guid sourceDocumentPublicId,
        IExecutionContext context,
        CancellationToken ct);
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
    IInventoryPhysicalAuthority inventory,
    IPurchasingTransactionCoordinator transactions)
{
    public Task<Result<PurchasingMutationReceipt>> CreateOrderAsync(
        CreatePurchaseOrderCommand command, IExecutionContext context, CancellationToken ct) =>
        WithPermission(PurchasingPermissions.OrderCreate, () => persistence.CreateOrderAsync(command, context, ct), context, ct);

    public Task<Result<PurchasingMutationReceipt>> ConfirmOrderAsync(
        Guid id, long version, string key, IExecutionContext context, CancellationToken ct) =>
        WithPermission(PurchasingPermissions.OrderConfirm, () => persistence.ConfirmOrderAsync(id, version, key, context, ct), context, ct);

    public Task<Result<PurchasingMutationReceipt>> CreateReceiptAsync(
        CreateGoodsReceiptCommand command, IExecutionContext context, CancellationToken ct) =>
        WithPermission(PurchasingPermissions.ReceiptCreate, () => persistence.CreateReceiptAsync(command, context, ct), context, ct);

    public async Task<Result<PurchasingMutationReceipt>> PostReceiptAsync(
        Guid receiptPublicId,
        string operationKey,
        IExecutionContext context,
        CancellationToken ct)
    {
        if (!await Granted(PurchasingPermissions.ReceiptPost, context, ct))
            return Denied<PurchasingMutationReceipt>();

        return await transactions.ExecuteAsync(async innerCt =>
        {
            var plan = await persistence.PrepareReceiptPostAsync(receiptPublicId, context, innerCt);
            if (plan.IsFailure) return Result<PurchasingMutationReceipt>.Failure(plan.Error!);

            var effects = new List<GoodsReceiptInventoryEffect>();
            var index = 0;
            foreach (var line in plan.Value!.Lines)
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

            return await persistence.CompleteReceiptPostAsync(
                receiptPublicId,
                effects,
                operationKey + ":complete",
                context,
                innerCt);
        }, ct);
    }

    public Task<Result<PurchasingMutationReceipt>> CreateInvoiceDraftAsync(
        CreateSupplierInvoiceDraftCommand command, IExecutionContext context, CancellationToken ct) =>
        WithPermission(PurchasingPermissions.InvoiceCreate, () => persistence.CreateInvoiceDraftAsync(command, context, ct), context, ct);

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
}
