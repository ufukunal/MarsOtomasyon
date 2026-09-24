using Mars.Application.Foundation.Approvals;
using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Application.Inventory;
using Mars.Domain.Inventory;
using Mars.Domain.Sales;

namespace Mars.Application.Sales;

public sealed record SalesTradeLineInput(
    int Sequence,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal Quantity,
    decimal UnitPrice,
    decimal LineDiscountPercent,
    decimal TaxPercent);

public sealed record CreateQuoteCommand(
    string Number,
    Guid CustomerPartyPublicId,
    string CurrencyCode,
    string? PaymentTerms,
    decimal DocumentDiscountPercent,
    IReadOnlyList<SalesTradeLineInput> Lines,
    string OperationKey);

public sealed record ReviseQuoteCommand(
    Guid QuotePublicId,
    long ExpectedVersion,
    string? PaymentTerms,
    decimal DocumentDiscountPercent,
    IReadOnlyList<SalesTradeLineInput> Lines,
    string OperationKey);

public sealed record QuoteConversionSelection(
    Guid QuoteLinePublicId,
    decimal Quantity);

public sealed record ConvertQuoteCommand(
    Guid QuotePublicId,
    long QuoteRevisionNumber,
    string OrderNumber,
    IReadOnlyList<QuoteConversionSelection> Lines,
    string OperationKey);

public sealed record CreateDirectOrderCommand(
    string Number,
    Guid CustomerPartyPublicId,
    string CurrencyCode,
    string? PaymentTerms,
    decimal DocumentDiscountPercent,
    IReadOnlyList<SalesTradeLineInput> Lines,
    string OperationKey);

public sealed record SalesOrderAmendmentDeltaInput(
    Guid SalesOrderLinePublicId,
    decimal QuantityDelta,
    decimal? NewUnitPrice,
    decimal? NewLineDiscountPercent,
    decimal? NewTaxPercent);

public sealed record CreateSalesOrderAmendmentCommand(
    Guid SalesOrderPublicId,
    long ExpectedOrderVersion,
    string? NewPaymentTerms,
    string Reason,
    IReadOnlyList<SalesOrderAmendmentDeltaInput> Deltas,
    string OperationKey);

public sealed record CreateReservationFromOrderCommand(
    Guid SalesOrderPublicId,
    long SalesOrderVersion,
    Guid SalesOrderLinePublicId,
    Guid WarehousePublicId,
    decimal Quantity,
    string OperationKey);

public sealed record ChangeSalesReservationCommand(
    Guid ReservationPublicId,
    decimal Quantity,
    string OperationKey);

public sealed record CreateDispatchLineInput(
    int Sequence,
    Guid SalesOrderLinePublicId,
    decimal Quantity,
    Guid? LocationPublicId,
    Guid? LotPublicId,
    Guid? SerialPublicId,
    Guid? ReservationPublicId);

public sealed record CreateDispatchCommand(
    string Number,
    Guid SalesOrderPublicId,
    long SalesOrderVersion,
    Guid WarehousePublicId,
    IReadOnlyList<CreateDispatchLineInput> Lines,
    string OperationKey);

public sealed record ReverseDispatchCommand(
    Guid DispatchPublicId,
    string ReversalNumber,
    string Reason,
    string OperationKey);

public sealed record CreateInvoiceDraftLineInput(
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

public sealed record CreateInvoiceDraftCommand(
    string Number,
    Guid CustomerPartyPublicId,
    SalesInvoiceSourceMode SourceMode,
    DateOnly DocumentDate,
    DateOnly DueDate,
    string CurrencyCode,
    decimal DocumentDiscountPercent,
    IReadOnlyList<CreateInvoiceDraftLineInput> Lines,
    string OperationKey);

public sealed record SalesMutationReceipt(
    Guid PublicId,
    string State,
    long Version,
    string CorrelationId);

public sealed record SalesDocumentListItem(
    Guid PublicId,
    string Number,
    string State,
    string CustomerCode,
    string CustomerName,
    long Version,
    DateTimeOffset CreatedAt);

public sealed record SalesDocumentLineView(
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

public sealed record SalesDocumentDetailView(
    Guid PublicId,
    string Number,
    string State,
    string CustomerCode,
    string CustomerName,
    string CurrencyCode,
    string? PaymentTerms,
    long Version,
    IReadOnlyList<SalesDocumentLineView> Lines);

public sealed record SalesInvoiceDraftView(
    Guid PublicId,
    string Number,
    string State,
    string CustomerCode,
    string CustomerLegalName,
    string CurrencyCode,
    DateOnly DocumentDate,
    DateOnly DueDate,
    decimal NetTotal,
    decimal TaxTotal,
    decimal GrossTotal,
    long Version,
    IReadOnlyList<SalesDocumentLineView> Lines);

public sealed record InvoiceSourceEligibilityView(
    SalesInvoiceSourceMode SourceMode,
    Guid SourceDocumentPublicId,
    Guid SourceLinePublicId,
    decimal SourceQuantity,
    decimal AlreadyDraftedQuantity,
    decimal EligibleQuantity);

public sealed record SalesApprovalTarget(
    Guid EntityPublicId,
    long SnapshotVersion,
    Guid CreatorActorId,
    string EntityType);

public sealed record SalesReservationPlan(
    Guid SalesOrderPublicId,
    long SalesOrderVersion,
    Guid SalesOrderLinePublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal ConversionFactorSnapshot,
    Guid WarehousePublicId,
    decimal RequestedQuantity);

public sealed record SalesReservationChangePlan(
    Guid ReservationPublicId,
    Guid SalesOrderPublicId,
    Guid SalesOrderLinePublicId,
    Guid WarehousePublicId,
    decimal RequestedQuantity);

public sealed record SalesReservationReleaseInstruction(
    Guid ReservationPublicId,
    Guid SalesOrderPublicId,
    Guid SalesOrderLinePublicId,
    decimal Quantity);

public sealed record SalesAmendmentActivationPlan(
    Guid AmendmentPublicId,
    Guid SalesOrderPublicId,
    bool RequiresApproval,
    SalesApprovalTarget ApprovalTarget,
    IReadOnlyList<SalesReservationReleaseInstruction> ReservationReleases);

public sealed record SalesDispatchPostLinePlan(
    Guid DispatchLinePublicId,
    Guid SalesOrderLinePublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal ConversionFactorSnapshot,
    decimal Quantity,
    InventoryPosition SourcePosition,
    Guid? ReservationPublicId);

public sealed record SalesDispatchPostPlan(
    Guid DispatchPublicId,
    Guid SalesOrderPublicId,
    Guid WarehousePublicId,
    IReadOnlyList<SalesDispatchPostLinePlan> Lines);

public sealed record SalesDispatchEffect(
    Guid DispatchLinePublicId,
    Guid InventoryMovementPublicId,
    Guid? OriginalInventoryMovementPublicId,
    bool IsReversal);

public sealed record SalesDispatchReverseLinePlan(
    Guid ReversalLinePublicId,
    Guid OriginalDispatchLinePublicId,
    Guid SalesOrderLinePublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal ConversionFactorSnapshot,
    decimal Quantity,
    InventoryPosition TargetPosition,
    Guid OriginalInventoryMovementPublicId);

public sealed record SalesDispatchReversePlan(
    Guid OriginalDispatchPublicId,
    Guid ReversalDispatchPublicId,
    Guid SalesOrderPublicId,
    Guid WarehousePublicId,
    IReadOnlyList<SalesDispatchReverseLinePlan> Lines);

public interface ISalesTransactionCoordinator
{
    Task<Result<T>> ExecuteAsync<T>(
        Func<CancellationToken, Task<Result<T>>> operation,
        CancellationToken cancellationToken);
}

public interface ISalesPersistence
{
    Task<IReadOnlyList<SalesDocumentListItem>> ListQuotesAsync(Guid companyId, CancellationToken ct);
    Task<SalesDocumentDetailView?> GetQuoteAsync(Guid companyId, Guid publicId, CancellationToken ct);
    Task<IReadOnlyList<SalesDocumentListItem>> ListOrdersAsync(Guid companyId, CancellationToken ct);
    Task<SalesDocumentDetailView?> GetOrderAsync(Guid companyId, Guid publicId, CancellationToken ct);
    Task<IReadOnlyList<SalesDocumentListItem>> ListDispatchesAsync(Guid companyId, CancellationToken ct);
    Task<SalesDocumentDetailView?> GetDispatchAsync(Guid companyId, Guid publicId, CancellationToken ct);
    Task<Guid?> GetDispatchWarehousePublicIdAsync(Guid companyId, Guid dispatchPublicId, CancellationToken ct);
    Task<IReadOnlyList<SalesDocumentListItem>> ListInvoicesAsync(Guid companyId, CancellationToken ct);
    Task<SalesInvoiceDraftView?> GetInvoiceAsync(Guid companyId, Guid publicId, CancellationToken ct);
    Task<IReadOnlyList<InvoiceSourceEligibilityView>> PreviewInvoiceSourceAsync(
        Guid companyId,
        SalesInvoiceSourceMode mode,
        Guid sourceDocumentPublicId,
        CancellationToken ct);

    Task<Result<SalesMutationReceipt>> CreateQuoteAsync(CreateQuoteCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> ReviseQuoteAsync(ReviseQuoteCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> SubmitQuoteApprovalAsync(Guid quotePublicId, long expectedVersion, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesApprovalTarget>> GetQuoteApprovalTargetAsync(Guid quotePublicId, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> SendQuoteToCustomerAsync(Guid quotePublicId, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> AcceptQuoteAsync(Guid quotePublicId, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> CancelQuoteAsync(Guid quotePublicId, string reason, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> ExpireQuoteAsync(Guid quotePublicId, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> ConvertQuoteAsync(ConvertQuoteCommand command, IExecutionContext context, CancellationToken ct);

    Task<Result<SalesMutationReceipt>> CreateDirectOrderAsync(CreateDirectOrderCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> SubmitOrderApprovalAsync(Guid orderPublicId, long expectedVersion, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesApprovalTarget>> GetOrderApprovalTargetAsync(Guid orderPublicId, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> ConfirmOrderAsync(Guid orderPublicId, long expectedVersion, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> HoldOrderAsync(Guid orderPublicId, long expectedVersion, string reason, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> ReleaseOrderAsync(Guid orderPublicId, long expectedVersion, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> CancelOrderRemainderAsync(Guid orderPublicId, long expectedVersion, string reason, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> CloseOrderAsync(Guid orderPublicId, long expectedVersion, string operationKey, IExecutionContext context, CancellationToken ct);

    Task<Result<SalesMutationReceipt>> CreateAmendmentAsync(CreateSalesOrderAmendmentCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> SubmitAmendmentApprovalAsync(Guid amendmentPublicId, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesApprovalTarget>> GetAmendmentApprovalTargetAsync(Guid amendmentPublicId, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesAmendmentActivationPlan>> PrepareAmendmentActivationAsync(Guid amendmentPublicId, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> ActivateAmendmentAsync(Guid amendmentPublicId, string operationKey, IExecutionContext context, CancellationToken ct);

    Task<Result<SalesReservationPlan>> GetReservationCreatePlanAsync(CreateReservationFromOrderCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesReservationChangePlan>> GetReservationChangePlanAsync(Guid reservationPublicId, decimal quantity, bool increase, IExecutionContext context, CancellationToken ct);

    Task<Result<SalesMutationReceipt>> CreateDispatchAsync(CreateDispatchCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> ReadyDispatchAsync(Guid dispatchPublicId, long expectedVersion, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesDispatchPostPlan>> PrepareDispatchPostAsync(Guid dispatchPublicId, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> CompleteDispatchPostAsync(Guid dispatchPublicId, IReadOnlyList<SalesDispatchEffect> effects, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> HandoffDispatchAsync(Guid dispatchPublicId, long expectedVersion, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> DeliverDispatchAsync(Guid dispatchPublicId, long expectedVersion, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> CancelDispatchAsync(Guid dispatchPublicId, long expectedVersion, string reason, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesDispatchReversePlan>> PrepareDispatchReverseAsync(ReverseDispatchCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> CompleteDispatchReverseAsync(Guid reversalDispatchPublicId, IReadOnlyList<SalesDispatchEffect> effects, string operationKey, IExecutionContext context, CancellationToken ct);

    Task<Result<SalesMutationReceipt>> CreateInvoiceDraftAsync(CreateInvoiceDraftCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> ReplaceInvoiceDraftAsync(Guid invoicePublicId, long expectedVersion, CreateInvoiceDraftCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<SalesMutationReceipt>> CancelInvoiceDraftAsync(Guid invoicePublicId, long expectedVersion, string reason, string operationKey, IExecutionContext context, CancellationToken ct);
}

public sealed class SalesQueryHandler(
    IPermissionEvaluator permissions,
    ISalesPersistence persistence)
{
    public async Task<Result<IReadOnlyList<SalesDocumentListItem>>> ListQuotesAsync(IExecutionContext context, CancellationToken ct) =>
        await ReadAsync(SalesPermissions.QuoteRead, () => persistence.ListQuotesAsync(context.CompanyId, ct), context, ct);

    public async Task<Result<SalesDocumentDetailView?>> GetQuoteAsync(Guid id, IExecutionContext context, CancellationToken ct) =>
        await ReadOneAsync(SalesPermissions.QuoteRead, () => persistence.GetQuoteAsync(context.CompanyId, id, ct), context, ct);

    public async Task<Result<IReadOnlyList<SalesDocumentListItem>>> ListOrdersAsync(IExecutionContext context, CancellationToken ct) =>
        await ReadAsync(SalesPermissions.OrderRead, () => persistence.ListOrdersAsync(context.CompanyId, ct), context, ct);

    public async Task<Result<SalesDocumentDetailView?>> GetOrderAsync(Guid id, IExecutionContext context, CancellationToken ct) =>
        await ReadOneAsync(SalesPermissions.OrderRead, () => persistence.GetOrderAsync(context.CompanyId, id, ct), context, ct);

    public async Task<Result<IReadOnlyList<SalesDocumentListItem>>> ListDispatchesAsync(IExecutionContext context, CancellationToken ct) =>
        await ReadAsync(SalesPermissions.DispatchRead, () => persistence.ListDispatchesAsync(context.CompanyId, ct), context, ct);

    public async Task<Result<SalesDocumentDetailView?>> GetDispatchAsync(Guid id, IExecutionContext context, CancellationToken ct) =>
        await ReadOneAsync(SalesPermissions.DispatchRead, () => persistence.GetDispatchAsync(context.CompanyId, id, ct), context, ct);

    public async Task<Result<IReadOnlyList<SalesDocumentListItem>>> ListInvoicesAsync(IExecutionContext context, CancellationToken ct) =>
        await ReadAsync(SalesPermissions.InvoiceRead, () => persistence.ListInvoicesAsync(context.CompanyId, ct), context, ct);

    public async Task<Result<SalesInvoiceDraftView?>> GetInvoiceAsync(Guid id, IExecutionContext context, CancellationToken ct)
    {
        if (!await Granted(SalesPermissions.InvoiceRead, context, ct))
            return Result<SalesInvoiceDraftView?>.Failure(Denied());
        return Result<SalesInvoiceDraftView?>.Success(await persistence.GetInvoiceAsync(context.CompanyId, id, ct));
    }

    public async Task<Result<IReadOnlyList<InvoiceSourceEligibilityView>>> PreviewInvoiceSourceAsync(
        SalesInvoiceSourceMode mode,
        Guid sourceDocumentPublicId,
        IExecutionContext context,
        CancellationToken ct)
    {
        if (!await Granted(SalesPermissions.InvoiceRead, context, ct))
            return Result<IReadOnlyList<InvoiceSourceEligibilityView>>.Failure(Denied());
        if (sourceDocumentPublicId == Guid.Empty || mode == SalesInvoiceSourceMode.Direct)
            return Result<IReadOnlyList<InvoiceSourceEligibilityView>>.Failure(
                Validation("sales.invoice.source.invalid", "Dispatch or Order source identity is required."));
        return Result<IReadOnlyList<InvoiceSourceEligibilityView>>.Success(
            await persistence.PreviewInvoiceSourceAsync(context.CompanyId, mode, sourceDocumentPublicId, ct));
    }

    private async Task<Result<IReadOnlyList<SalesDocumentListItem>>> ReadAsync(
        string permission,
        Func<Task<IReadOnlyList<SalesDocumentListItem>>> read,
        IExecutionContext context,
        CancellationToken ct)
    {
        if (!await Granted(permission, context, ct))
            return Result<IReadOnlyList<SalesDocumentListItem>>.Failure(Denied());
        return Result<IReadOnlyList<SalesDocumentListItem>>.Success(await read());
    }

    private async Task<Result<SalesDocumentDetailView?>> ReadOneAsync(
        string permission,
        Func<Task<SalesDocumentDetailView?>> read,
        IExecutionContext context,
        CancellationToken ct)
    {
        if (!await Granted(permission, context, ct))
            return Result<SalesDocumentDetailView?>.Failure(Denied());
        return Result<SalesDocumentDetailView?>.Success(await read());
    }

    private Task<bool> Granted(string permission, IExecutionContext context, CancellationToken ct) =>
        permissions.IsGrantedAsync(context.ActorId, context.CompanyId, permission, ct);

    private static ApplicationError Denied() =>
        new(ErrorCategory.Authorization, "sales.permission.denied", "The required Sales permission is not granted.");
    private static ApplicationError Validation(string code,string message) =>
        new(ErrorCategory.Validation,code,message);
}

public sealed class SalesCommandHandler(
    IPermissionEvaluator permissions,
    ISalesPersistence persistence,
    IApprovalDecisionAuthority approvals,
    IInventoryReservationAuthority reservations,
    IInventoryPhysicalAuthority inventory,
    IWarehouseAccessEvaluator warehouseAccess,
    ISalesTransactionCoordinator transactions)
{
    public Task<Result<SalesMutationReceipt>> CreateQuoteAsync(CreateQuoteCommand command, IExecutionContext context, CancellationToken ct) =>
        WithPermission(SalesPermissions.QuoteCreate, () => persistence.CreateQuoteAsync(command, context, ct), context, ct);

    public Task<Result<SalesMutationReceipt>> ReviseQuoteAsync(ReviseQuoteCommand command, IExecutionContext context, CancellationToken ct) =>
        WithPermission(SalesPermissions.QuoteRevise, () => persistence.ReviseQuoteAsync(command, context, ct), context, ct);

    public Task<Result<SalesMutationReceipt>> SubmitQuoteApprovalAsync(Guid id,long version,string key,IExecutionContext context,CancellationToken ct) =>
        WithPermission(SalesPermissions.QuoteSubmitApproval, () => persistence.SubmitQuoteApprovalAsync(id,version,key,context,ct), context, ct);

    public Task<Result<ApprovalDecisionReceipt>> ApproveQuoteAsync(
        Guid id,
        ApprovalDecisionKind decision,
        string? reason,
        string operationKey,
        IExecutionContext context,
        CancellationToken ct) =>
        Decide(
            SalesPermissions.QuoteApprove,
            () => persistence.GetQuoteApprovalTargetAsync(id, context, ct),
            decision,
            reason,
            operationKey,
            context,
            ct);

    public async Task<Result<SalesMutationReceipt>> SendQuoteToCustomerAsync(Guid id,string key,IExecutionContext context,CancellationToken ct)
    {
        if (!await Granted(SalesPermissions.QuoteSendCustomer,context,ct)) return Denied<SalesMutationReceipt>();
        var target=await persistence.GetQuoteApprovalTargetAsync(id,context,ct);
        if (target.IsFailure) return Result<SalesMutationReceipt>.Failure(target.Error!);
        if (!await approvals.IsApprovedAsync(context.CompanyId,"Sales",target.Value!.EntityType,target.Value.EntityPublicId,target.Value.SnapshotVersion,ct))
            return Business<SalesMutationReceipt>("sales.quote.approval.required","The current Quote revision requires approval before customer review.");
        return await persistence.SendQuoteToCustomerAsync(id,key,context,ct);
    }

    public async Task<Result<SalesMutationReceipt>> AcceptQuoteAsync(Guid id,string key,IExecutionContext context,CancellationToken ct)
    {
        if (!await Granted(SalesPermissions.QuoteSendCustomer,context,ct)) return Denied<SalesMutationReceipt>();
        var target=await persistence.GetQuoteApprovalTargetAsync(id,context,ct);
        if (target.IsFailure) return Result<SalesMutationReceipt>.Failure(target.Error!);
        if (!await approvals.IsApprovedAsync(context.CompanyId,"Sales",target.Value!.EntityType,target.Value.EntityPublicId,target.Value.SnapshotVersion,ct))
            return Business<SalesMutationReceipt>("sales.quote.approval.required","The current Quote revision requires approval before acceptance.");
        return await persistence.AcceptQuoteAsync(id,key,context,ct);
    }

    public Task<Result<SalesMutationReceipt>> CancelQuoteAsync(Guid id,string reason,string key,IExecutionContext context,CancellationToken ct) =>
        WithPermission(SalesPermissions.QuoteCancel,()=>persistence.CancelQuoteAsync(id,reason,key,context,ct),context,ct);

    public Task<Result<SalesMutationReceipt>> ExpireQuoteAsync(Guid id,string key,IExecutionContext context,CancellationToken ct) =>
        WithPermission(SalesPermissions.QuoteCancel,()=>persistence.ExpireQuoteAsync(id,key,context,ct),context,ct);

    public Task<Result<SalesMutationReceipt>> ConvertQuoteAsync(ConvertQuoteCommand command,IExecutionContext context,CancellationToken ct) =>
        WithPermission(SalesPermissions.QuoteConvertOrder,()=>persistence.ConvertQuoteAsync(command,context,ct),context,ct);

    public Task<Result<SalesMutationReceipt>> CreateDirectOrderAsync(CreateDirectOrderCommand command,IExecutionContext context,CancellationToken ct) =>
        WithPermission(SalesPermissions.OrderCreate,()=>persistence.CreateDirectOrderAsync(command,context,ct),context,ct);

    public Task<Result<SalesMutationReceipt>> SubmitOrderApprovalAsync(Guid id,long version,string key,IExecutionContext context,CancellationToken ct) =>
        WithPermission(SalesPermissions.OrderSubmitApproval,()=>persistence.SubmitOrderApprovalAsync(id,version,key,context,ct),context,ct);

    public Task<Result<ApprovalDecisionReceipt>> ApproveOrderAsync(Guid id,ApprovalDecisionKind decision,string? reason,string key,IExecutionContext context,CancellationToken ct) =>
        Decide(SalesPermissions.OrderApprove,()=>persistence.GetOrderApprovalTargetAsync(id,context,ct),decision,reason,key,context,ct);

    public async Task<Result<SalesMutationReceipt>> ConfirmOrderAsync(Guid id,long version,string key,IExecutionContext context,CancellationToken ct)
    {
        if (!await Granted(SalesPermissions.OrderConfirm,context,ct)) return Denied<SalesMutationReceipt>();
        var target=await persistence.GetOrderApprovalTargetAsync(id,context,ct);
        if (target.IsFailure) return Result<SalesMutationReceipt>.Failure(target.Error!);
        var inherited=target.Value!.EntityType=="SalesOrderInheritedApproval";
        if (!inherited && !await approvals.IsApprovedAsync(context.CompanyId,"Sales","SalesOrder",target.Value.EntityPublicId,target.Value.SnapshotVersion,ct))
            return Business<SalesMutationReceipt>("sales.order.approval.required","Direct or commercially changed Sales Order requires approval before confirmation.");
        return await persistence.ConfirmOrderAsync(id,version,key,context,ct);
    }

    public Task<Result<SalesMutationReceipt>> HoldOrderAsync(Guid id,long version,string reason,string key,IExecutionContext context,CancellationToken ct) =>
        WithPermission(SalesPermissions.OrderHold,()=>persistence.HoldOrderAsync(id,version,reason,key,context,ct),context,ct);
    public Task<Result<SalesMutationReceipt>> ReleaseOrderAsync(Guid id,long version,string key,IExecutionContext context,CancellationToken ct) =>
        WithPermission(SalesPermissions.OrderReleaseHold,()=>persistence.ReleaseOrderAsync(id,version,key,context,ct),context,ct);
    public Task<Result<SalesMutationReceipt>> CancelOrderRemainderAsync(Guid id,long version,string reason,string key,IExecutionContext context,CancellationToken ct) =>
        WithPermission(SalesPermissions.OrderCancelRemaining,()=>persistence.CancelOrderRemainderAsync(id,version,reason,key,context,ct),context,ct);
    public Task<Result<SalesMutationReceipt>> CloseOrderAsync(Guid id,long version,string key,IExecutionContext context,CancellationToken ct) =>
        WithPermission(SalesPermissions.OrderClose,()=>persistence.CloseOrderAsync(id,version,key,context,ct),context,ct);

    public Task<Result<SalesMutationReceipt>> CreateAmendmentAsync(CreateSalesOrderAmendmentCommand command,IExecutionContext context,CancellationToken ct) =>
        WithPermission(SalesPermissions.OrderAmend,()=>persistence.CreateAmendmentAsync(command,context,ct),context,ct);

    public Task<Result<SalesMutationReceipt>> SubmitAmendmentApprovalAsync(Guid id,string key,IExecutionContext context,CancellationToken ct) =>
        WithPermission(SalesPermissions.OrderSubmitApproval,()=>persistence.SubmitAmendmentApprovalAsync(id,key,context,ct),context,ct);

    public Task<Result<ApprovalDecisionReceipt>> ApproveAmendmentAsync(Guid id,ApprovalDecisionKind decision,string? reason,string key,IExecutionContext context,CancellationToken ct) =>
        Decide(SalesPermissions.OrderApprove,()=>persistence.GetAmendmentApprovalTargetAsync(id,context,ct),decision,reason,key,context,ct);

    public async Task<Result<SalesMutationReceipt>> ActivateAmendmentAsync(Guid id,string key,IExecutionContext context,CancellationToken ct)
    {
        if (!await Granted(SalesPermissions.OrderActivateAmendment,context,ct)) return Denied<SalesMutationReceipt>();
        return await transactions.ExecuteAsync(async innerCt =>
        {
            var plan=await persistence.PrepareAmendmentActivationAsync(id,context,innerCt);
            if (plan.IsFailure) return Result<SalesMutationReceipt>.Failure(plan.Error!);
            if (plan.Value!.RequiresApproval &&
                !await approvals.IsApprovedAsync(context.CompanyId,"Sales",plan.Value.ApprovalTarget.EntityType,
                    plan.Value.ApprovalTarget.EntityPublicId,plan.Value.ApprovalTarget.SnapshotVersion,innerCt))
                return Business<SalesMutationReceipt>("sales.order.amendment.approval.required","Exposure or term-changing amendment requires approval.");

            var n=0;
            foreach (var release in plan.Value.ReservationReleases)
            {
                var result=await reservations.ReleaseAsync(
                    new ChangeReservationCommand(
                        release.ReservationPublicId,
                        release.Quantity,
                        InventorySourceIdentity.Create("Sales","SalesOrder",release.SalesOrderPublicId,release.SalesOrderLinePublicId),
                        DerivedKey(key,$"release-{++n}")),
                    context,
                    innerCt);
                if (result.IsFailure) return Result<SalesMutationReceipt>.Failure(result.Error!);
            }
            return await persistence.ActivateAmendmentAsync(id,key,context,innerCt);
        },ct);
    }

    public async Task<Result<InventoryReservationReceipt>> CreateReservationAsync(CreateReservationFromOrderCommand command,IExecutionContext context,CancellationToken ct)
    {
        if (!await Granted(InventoryPermissions.ReservationCreate,context,ct))
            return Result<InventoryReservationReceipt>.Failure(DeniedError());
        var plan=await persistence.GetReservationCreatePlanAsync(command,context,ct);
        if (plan.IsFailure) return Result<InventoryReservationReceipt>.Failure(plan.Error!);
        if (!await warehouseAccess.IsGrantedAsync(context.ActorId,context.CompanyId,plan.Value!.WarehousePublicId,ct))
            return Result<InventoryReservationReceipt>.Failure(WarehouseDenied());
        return await reservations.CreateAsync(
            new CreateReservationCommand(
                plan.Value.SalesOrderPublicId,
                plan.Value.SalesOrderVersion,
                plan.Value.SalesOrderLinePublicId,
                plan.Value.ProductPublicId,
                plan.Value.VariantPublicId,
                plan.Value.WarehousePublicId,
                plan.Value.UomPublicId,
                plan.Value.RequestedQuantity,
                plan.Value.ConversionFactorSnapshot,
                command.OperationKey),
            context,ct);
    }

    public async Task<Result<InventoryReservationReceipt>> IncreaseReservationAsync(ChangeSalesReservationCommand command,IExecutionContext context,CancellationToken ct) =>
        await ChangeReservation(command,true,InventoryPermissions.ReservationIncrease,context,ct);

    public async Task<Result<InventoryReservationReceipt>> ReleaseReservationAsync(ChangeSalesReservationCommand command,IExecutionContext context,CancellationToken ct) =>
        await ChangeReservation(command,false,InventoryPermissions.ReservationRelease,context,ct);

    public async Task<Result<SalesMutationReceipt>> CreateDispatchAsync(CreateDispatchCommand command,IExecutionContext context,CancellationToken ct)
    {
        if (!await Granted(SalesPermissions.DispatchCreate,context,ct)) return Denied<SalesMutationReceipt>();
        if (!await warehouseAccess.IsGrantedAsync(context.ActorId,context.CompanyId,command.WarehousePublicId,ct))
            return Result<SalesMutationReceipt>.Failure(WarehouseDenied());
        return await persistence.CreateDispatchAsync(command,context,ct);
    }

    public Task<Result<SalesMutationReceipt>> ReadyDispatchAsync(Guid id,long version,string key,IExecutionContext context,CancellationToken ct) =>
        WithDispatchPermissionAndWarehouse(
            SalesPermissions.DispatchEditDraft,id,
            ()=>persistence.ReadyDispatchAsync(id,version,key,context,ct),context,ct);

    public async Task<Result<SalesMutationReceipt>> PostDispatchAsync(Guid id,string key,IExecutionContext context,CancellationToken ct)
    {
        if (!await Granted(SalesPermissions.DispatchPost,context,ct)) return Denied<SalesMutationReceipt>();
        return await transactions.ExecuteAsync(async innerCt =>
        {
            var plan=await persistence.PrepareDispatchPostAsync(id,context,innerCt);
            if (plan.IsFailure) return Result<SalesMutationReceipt>.Failure(plan.Error!);
            if (!await warehouseAccess.IsGrantedAsync(context.ActorId,context.CompanyId,plan.Value!.WarehousePublicId,innerCt))
                return Result<SalesMutationReceipt>.Failure(WarehouseDenied());

            var effects=new List<SalesDispatchEffect>(plan.Value.Lines.Count);
            var index=0;
            foreach(var line in plan.Value.Lines)
            {
                var movement=await inventory.PostAsync(
                    new InventoryMovementCommand(
                        line.ProductPublicId,line.VariantPublicId,line.UomPublicId,line.Quantity,
                        line.ConversionFactorSnapshot,line.SourcePosition,null,
                        InventorySourceIdentity.Create("Sales","Dispatch",plan.Value.DispatchPublicId,line.DispatchLinePublicId),
                        null,DerivedKey(key,$"stock-{++index}")),
                    context,innerCt);
                if(movement.IsFailure) return Result<SalesMutationReceipt>.Failure(movement.Error!);

                if(line.ReservationPublicId.HasValue)
                {
                    var consumed=await reservations.ConsumeAsync(
                        new ChangeReservationCommand(
                            line.ReservationPublicId.Value,line.Quantity,
                            InventorySourceIdentity.Create("Sales","Dispatch",plan.Value.DispatchPublicId,line.DispatchLinePublicId),
                            DerivedKey(key,$"consume-{index}")),
                        context,innerCt);
                    if(consumed.IsFailure) return Result<SalesMutationReceipt>.Failure(consumed.Error!);
                }

                effects.Add(new SalesDispatchEffect(line.DispatchLinePublicId,movement.Value!.MovementPublicId,null,false));
            }
            return await persistence.CompleteDispatchPostAsync(id,effects,key,context,innerCt);
        },ct);
    }

    public Task<Result<SalesMutationReceipt>> HandoffDispatchAsync(Guid id,long version,string key,IExecutionContext context,CancellationToken ct) =>
        WithDispatchPermissionAndWarehouse(
            SalesPermissions.DispatchHandoff,id,
            ()=>persistence.HandoffDispatchAsync(id,version,key,context,ct),context,ct);
    public Task<Result<SalesMutationReceipt>> DeliverDispatchAsync(Guid id,long version,string key,IExecutionContext context,CancellationToken ct) =>
        WithDispatchPermissionAndWarehouse(
            SalesPermissions.DispatchHandoff,id,
            ()=>persistence.DeliverDispatchAsync(id,version,key,context,ct),context,ct);
    public Task<Result<SalesMutationReceipt>> CancelDispatchAsync(Guid id,long version,string reason,string key,IExecutionContext context,CancellationToken ct) =>
        WithDispatchPermissionAndWarehouse(
            SalesPermissions.DispatchEditDraft,id,
            ()=>persistence.CancelDispatchAsync(id,version,reason,key,context,ct),context,ct);

    public async Task<Result<SalesMutationReceipt>> ReverseDispatchAsync(ReverseDispatchCommand command,IExecutionContext context,CancellationToken ct)
    {
        if(!await Granted(SalesPermissions.DispatchReverse,context,ct)) return Denied<SalesMutationReceipt>();
        return await transactions.ExecuteAsync(async innerCt =>
        {
            var plan=await persistence.PrepareDispatchReverseAsync(command,context,innerCt);
            if(plan.IsFailure) return Result<SalesMutationReceipt>.Failure(plan.Error!);
            if(!await warehouseAccess.IsGrantedAsync(context.ActorId,context.CompanyId,plan.Value!.WarehousePublicId,innerCt))
                return Result<SalesMutationReceipt>.Failure(WarehouseDenied());

            var effects=new List<SalesDispatchEffect>(plan.Value.Lines.Count);
            var index=0;
            foreach(var line in plan.Value.Lines)
            {
                var movement=await inventory.PostAsync(
                    new InventoryMovementCommand(
                        line.ProductPublicId,line.VariantPublicId,line.UomPublicId,line.Quantity,
                        line.ConversionFactorSnapshot,null,line.TargetPosition,
                        InventorySourceIdentity.Create("Sales","DispatchReversal",plan.Value.ReversalDispatchPublicId,line.ReversalLinePublicId),
                        line.OriginalInventoryMovementPublicId,DerivedKey(command.OperationKey,$"stock-reverse-{++index}")),
                    context,innerCt);
                if(movement.IsFailure) return Result<SalesMutationReceipt>.Failure(movement.Error!);
                effects.Add(new SalesDispatchEffect(line.ReversalLinePublicId,movement.Value!.MovementPublicId,line.OriginalInventoryMovementPublicId,true));
            }
            return await persistence.CompleteDispatchReverseAsync(plan.Value.ReversalDispatchPublicId,effects,command.OperationKey,context,innerCt);
        },ct);
    }

    public Task<Result<SalesMutationReceipt>> CreateInvoiceDraftAsync(CreateInvoiceDraftCommand command,IExecutionContext context,CancellationToken ct) =>
        WithPermission(SalesPermissions.InvoiceCreate,()=>persistence.CreateInvoiceDraftAsync(command,context,ct),context,ct);

    public Task<Result<SalesMutationReceipt>> ReplaceInvoiceDraftAsync(Guid id,long version,CreateInvoiceDraftCommand command,IExecutionContext context,CancellationToken ct) =>
        WithPermission(SalesPermissions.InvoiceEditDraft,()=>persistence.ReplaceInvoiceDraftAsync(id,version,command,context,ct),context,ct);

    public Task<Result<SalesMutationReceipt>> CancelInvoiceDraftAsync(Guid id,long version,string reason,string key,IExecutionContext context,CancellationToken ct) =>
        WithPermission(SalesPermissions.InvoiceEditDraft,()=>persistence.CancelInvoiceDraftAsync(id,version,reason,key,context,ct),context,ct);

    private async Task<Result<InventoryReservationReceipt>> ChangeReservation(
        ChangeSalesReservationCommand command,bool increase,string permission,IExecutionContext context,CancellationToken ct)
    {
        if(!await Granted(permission,context,ct))
            return Result<InventoryReservationReceipt>.Failure(DeniedError());
        var plan=await persistence.GetReservationChangePlanAsync(command.ReservationPublicId,command.Quantity,increase,context,ct);
        if(plan.IsFailure) return Result<InventoryReservationReceipt>.Failure(plan.Error!);
        if(!await warehouseAccess.IsGrantedAsync(context.ActorId,context.CompanyId,plan.Value!.WarehousePublicId,ct))
            return Result<InventoryReservationReceipt>.Failure(WarehouseDenied());
        var inventoryCommand=new ChangeReservationCommand(
            command.ReservationPublicId,command.Quantity,
            InventorySourceIdentity.Create("Sales","SalesOrder",plan.Value.SalesOrderPublicId,plan.Value.SalesOrderLinePublicId),
            command.OperationKey);
        return increase
            ? await reservations.IncreaseAsync(inventoryCommand,context,ct)
            : await reservations.ReleaseAsync(inventoryCommand,context,ct);
    }

    private async Task<Result<ApprovalDecisionReceipt>> Decide(
        string permission,
        Func<Task<Result<SalesApprovalTarget>>> targetReader,
        ApprovalDecisionKind decision,
        string? reason,
        string operationKey,
        IExecutionContext context,
        CancellationToken ct)
    {
        if(!await Granted(permission,context,ct))
            return Result<ApprovalDecisionReceipt>.Failure(DeniedError());
        var target=await targetReader();
        if(target.IsFailure) return Result<ApprovalDecisionReceipt>.Failure(target.Error!);
        return await approvals.DecideAsync(
            new ApprovalDecisionCommand(
                "Sales",target.Value!.EntityType,target.Value.EntityPublicId,target.Value.SnapshotVersion,
                target.Value.CreatorActorId,decision,reason,operationKey),
            context,ct);
    }

    private async Task<Result<T>> WithDispatchPermissionAndWarehouse<T>(
        string permission,
        Guid dispatchPublicId,
        Func<Task<Result<T>>> action,
        IExecutionContext context,
        CancellationToken ct)
    {
        if(!await Granted(permission,context,ct)) return Denied<T>();
        var warehousePublicId=await persistence.GetDispatchWarehousePublicIdAsync(
            context.CompanyId,dispatchPublicId,ct);
        if(!warehousePublicId.HasValue)
            return Result<T>.Failure(
                new ApplicationError(ErrorCategory.NotFound,"sales.dispatch.not_found","Dispatch was not found."));
        if(!await warehouseAccess.IsGrantedAsync(
               context.ActorId,context.CompanyId,warehousePublicId.Value,ct))
            return Result<T>.Failure(WarehouseDenied());
        return await action();
    }

    private async Task<Result<T>> WithPermission<T>(
        string permission,
        Func<Task<Result<T>>> action,
        IExecutionContext context,
        CancellationToken ct)
    {
        if(!await Granted(permission,context,ct)) return Denied<T>();
        return await action();
    }

    private Task<bool> Granted(string permission,IExecutionContext context,CancellationToken ct) =>
        permissions.IsGrantedAsync(context.ActorId,context.CompanyId,permission,ct);

    private static Result<T> Denied<T>() => Result<T>.Failure(DeniedError());
    private static ApplicationError DeniedError() =>
        new(ErrorCategory.Authorization,"sales.permission.denied","The required permission is not granted.");
    private static ApplicationError WarehouseDenied() =>
        new(ErrorCategory.Authorization,"sales.warehouse.scope_denied","The actor has no active access grant for this Warehouse.");
    private static Result<T> Business<T>(string code,string message) =>
        Result<T>.Failure(new ApplicationError(ErrorCategory.BusinessRule,code,message));
    private static string DerivedKey(string root,string suffix)
    {
        var key=$"{root}:{suffix}";
        return key.Length<=180?key:key[..180];
    }
}
