using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Domain.Finance;

namespace Mars.Application.Finance;

public sealed record FinanceMutationReceipt(
    Guid PublicId,
    string State,
    long Version,
    string CorrelationId);

public sealed record FinanceTransactionView(
    Guid PublicId,
    string Kind,
    string State,
    string CurrencyCode,
    decimal Amount,
    decimal BaseAmount,
    Guid? PartyPublicId,
    string? PartyRole,
    DateOnly DocumentDate,
    DateOnly PostingDate,
    Guid? SourceDocumentPublicId,
    Guid? ReversalOfTransactionPublicId,
    string? Reason,
    DateTimeOffset PostedAt);

public sealed record FinanceAccountBalanceView(
    Guid PartyPublicId,
    string PartyCode,
    string PartyName,
    string Role,
    string CurrencyCode,
    decimal Balance);

public sealed record FinanceMoneyAccountView(
    Guid PublicId,
    string Kind,
    string Code,
    string Name,
    string CurrencyCode,
    string State,
    Guid? BranchId,
    string? MaskedIban,
    decimal Balance);

public sealed record FinancePostingPeriodView(
    Guid PublicId,
    DateOnly StartDate,
    DateOnly EndDate,
    string State,
    long Version);

public sealed record FinanceValuationPoolView(
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    string CurrencyCode,
    decimal BaseQuantity,
    decimal CarryingValue,
    decimal? MovingAverageUnitValue);

public sealed record FinanceRiskView(
    Guid PartyPublicId,
    decimal? CreditLimit,
    bool ManualHold,
    string? HoldReason,
    decimal ReceivableExposure,
    decimal CreditBalance,
    decimal EffectiveExposure,
    bool LimitExceeded);

public sealed record FinanceStatementLineView(
    Guid PublicId,
    Guid BatchPublicId,
    Guid BankAccountPublicId,
    DateOnly BookingDate,
    DateOnly? ValueDate,
    decimal Amount,
    string CurrencyCode,
    string? ExternalId,
    string Fingerprint,
    string? Description,
    string State,
    decimal MatchedAmount);

public sealed record CreateFinanceMoneyAccountCommand(
    FinanceMoneyAccountKind Kind,
    string Code,
    string Name,
    string CurrencyCode,
    Guid? BranchId,
    string? Iban,
    decimal OpeningBalance,
    DateOnly PostingDate,
    string OperationKey);

public sealed record PostCollectionCommand(
    Guid CustomerPartyPublicId,
    FinanceMoneyAccountKind TargetAccountKind,
    Guid TargetAccountPublicId,
    decimal Amount,
    string CurrencyCode,
    DateOnly DocumentDate,
    DateOnly PostingDate,
    string? Reason,
    string OperationKey);

public sealed record PostSupplierPaymentCommand(
    Guid SupplierPartyPublicId,
    FinanceMoneyAccountKind SourceAccountKind,
    Guid SourceAccountPublicId,
    decimal Amount,
    string CurrencyCode,
    DateOnly DocumentDate,
    DateOnly PostingDate,
    string? Reason,
    string OperationKey);

public sealed record PostRefundCommand(
    FinancePartyRole PartyRole,
    Guid PartyPublicId,
    FinanceMoneyAccountKind AccountKind,
    Guid AccountPublicId,
    decimal Amount,
    string CurrencyCode,
    DateOnly DocumentDate,
    DateOnly PostingDate,
    string Reason,
    string OperationKey);

public sealed record PostRoleNettingCommand(
    Guid PartyPublicId,
    decimal Amount,
    string CurrencyCode,
    DateOnly DocumentDate,
    DateOnly PostingDate,
    string Reason,
    string OperationKey);

public sealed record PostTreasuryTransferCommand(
    FinanceMoneyAccountKind SourceKind,
    Guid SourceAccountPublicId,
    FinanceMoneyAccountKind TargetKind,
    Guid TargetAccountPublicId,
    decimal Amount,
    string CurrencyCode,
    DateOnly DocumentDate,
    DateOnly PostingDate,
    string? Reason,
    string OperationKey);

public sealed record CreatePostingPeriodCommand(
    DateOnly StartDate,
    DateOnly EndDate,
    string OperationKey);

public sealed record ChangePostingPeriodStateCommand(
    Guid PeriodPublicId,
    long ExpectedVersion,
    FinancePostingPeriodState State,
    string? Reason,
    string OperationKey);

public sealed record SetCustomerRiskCommand(
    Guid PartyPublicId,
    decimal? CreditLimit,
    bool ManualHold,
    string? HoldReason,
    string OperationKey);

public sealed record ImportStatementLineInput(
    DateOnly BookingDate,
    DateOnly? ValueDate,
    decimal Amount,
    string CurrencyCode,
    string? ExternalId,
    string? Description);

public sealed record ImportBankStatementCommand(
    Guid BankAccountPublicId,
    string SourceName,
    string? SourceReference,
    IReadOnlyList<ImportStatementLineInput> Lines,
    string OperationKey);

public sealed record ReconcileStatementCommand(
    Guid StatementLinePublicId,
    Guid BankLedgerEntryPublicId,
    decimal MatchedAmount,
    string OperationKey);

public sealed record FinanceReceiptValuationLine(
    Guid GoodsReceiptLinePublicId,
    Guid InventoryMovementPublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal BaseQuantity,
    decimal ProvisionalBaseValue);

public sealed record FinanceGoodsReceiptValuationCommand(
    Guid GoodsReceiptPublicId,
    DateOnly PostingDate,
    IReadOnlyList<FinanceReceiptValuationLine> Lines,
    string OperationKey);

public sealed record FinanceDispatchValuationLine(
    Guid DispatchLinePublicId,
    Guid PhysicalSourcePublicId,
    Guid InventoryMovementPublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal BaseQuantity);

public sealed record FinanceDispatchValuationCommand(
    Guid DispatchPublicId,
    DateOnly PostingDate,
    IReadOnlyList<FinanceDispatchValuationLine> Lines,
    string OperationKey);

public sealed record FinanceDispatchReversalLine(
    Guid OriginalInventoryMovementPublicId,
    Guid ReversalInventoryMovementPublicId);

public sealed record FinanceDispatchReversalCommand(
    Guid DispatchPublicId,
    DateOnly PostingDate,
    IReadOnlyList<FinanceDispatchReversalLine> Lines,
    string OperationKey);

public sealed record FinanceSalesInvoiceCogsLine(
    Guid SalesInvoiceLinePublicId,
    Guid DispatchPublicId,
    Guid DispatchLinePublicId,
    decimal BaseQuantity);

public sealed record FinanceSalesInvoicePostCommand(
    Guid SalesInvoicePublicId,
    Guid CustomerPartyPublicId,
    string CurrencyCode,
    decimal GrossAmount,
    DateOnly DocumentDate,
    DateOnly DueDate,
    DateOnly PostingDate,
    IReadOnlyList<FinanceSalesInvoiceCogsLine> DispatchLines,
    string OperationKey);

public sealed record FinanceCountValuationLine(
    Guid CountLinePublicId,
    Guid InventoryMovementPublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal BaseQuantityEffect,
    decimal? ExplicitUnitBaseValue);

public sealed record FinanceCountValuationCommand(
    Guid CountPublicId,
    DateOnly PostingDate,
    IReadOnlyList<FinanceCountValuationLine> Lines,
    string OperationKey);

public sealed record FinanceScrapValuationCommand(
    Guid ScrapPublicId,
    Guid InventoryMovementPublicId,
    Guid ProductPublicId,
    Guid? VariantPublicId,
    Guid UomPublicId,
    decimal BaseQuantity,
    DateOnly PostingDate,
    string OperationKey);

public interface IFinancePersistence
{
    Task<IReadOnlyList<FinanceTransactionView>> ListTransactionsAsync(Guid companyId, CancellationToken ct);
    Task<IReadOnlyList<FinanceAccountBalanceView>> ListAccountBalancesAsync(Guid companyId, CancellationToken ct);
    Task<IReadOnlyList<FinanceMoneyAccountView>> ListMoneyAccountsAsync(Guid companyId, CancellationToken ct);
    Task<IReadOnlyList<FinancePostingPeriodView>> ListPostingPeriodsAsync(Guid companyId, CancellationToken ct);
    Task<IReadOnlyList<FinanceValuationPoolView>> ListValuationPoolsAsync(Guid companyId, CancellationToken ct);
    Task<IReadOnlyList<FinanceStatementLineView>> ListStatementLinesAsync(Guid companyId, CancellationToken ct);
    Task<FinanceRiskView?> GetCustomerRiskAsync(Guid companyId, Guid partyPublicId, CancellationToken ct);

    Task<Result<FinanceMutationReceipt>> CreateMoneyAccountAsync(CreateFinanceMoneyAccountCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> PostCollectionAsync(PostCollectionCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> PostSupplierPaymentAsync(PostSupplierPaymentCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> PostRefundAsync(PostRefundCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> PostRoleNettingAsync(PostRoleNettingCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> PostTreasuryTransferAsync(PostTreasuryTransferCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> ReverseTransactionAsync(Guid transactionPublicId, string reason, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> CreatePostingPeriodAsync(CreatePostingPeriodCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> ChangePostingPeriodStateAsync(ChangePostingPeriodStateCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> SetCustomerRiskAsync(SetCustomerRiskCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> ImportStatementAsync(ImportBankStatementCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> ReconcileStatementAsync(ReconcileStatementCommand command, IExecutionContext context, CancellationToken ct);
}

public interface IFinanceValuationAuthority
{
    Task<Result<FinanceMutationReceipt>> PostGoodsReceiptAsync(
        FinanceGoodsReceiptValuationCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> ReverseGoodsReceiptAsync(
        Guid goodsReceiptPublicId, DateOnly postingDate, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> PostDispatchAsync(
        FinanceDispatchValuationCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> ReverseDispatchAsync(
        FinanceDispatchReversalCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> PostSalesInvoiceAsync(
        FinanceSalesInvoicePostCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> ReverseSalesInvoiceAsync(
        Guid salesInvoicePublicId, DateOnly postingDate, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> PostCountAdjustmentAsync(
        FinanceCountValuationCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> ReverseCountAdjustmentAsync(
        Guid countPublicId, DateOnly postingDate, string operationKey, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> PostScrapAsync(
        FinanceScrapValuationCommand command, IExecutionContext context, CancellationToken ct);
    Task<Result<FinanceMutationReceipt>> ReverseScrapAsync(
        Guid scrapPublicId, DateOnly postingDate, string operationKey, IExecutionContext context, CancellationToken ct);
}

public sealed class FinanceQueryHandler(
    IPermissionEvaluator permissions,
    IFinancePersistence persistence)
{
    public Task<Result<IReadOnlyList<FinanceTransactionView>>> ListTransactionsAsync(IExecutionContext c,CancellationToken ct) =>
        Read(FinancePermissions.AccountRead,()=>persistence.ListTransactionsAsync(c.CompanyId,ct),c,ct);
    public Task<Result<IReadOnlyList<FinanceAccountBalanceView>>> ListAccountBalancesAsync(IExecutionContext c,CancellationToken ct) =>
        Read(FinancePermissions.AccountRead,()=>persistence.ListAccountBalancesAsync(c.CompanyId,ct),c,ct);
    public Task<Result<IReadOnlyList<FinanceMoneyAccountView>>> ListMoneyAccountsAsync(IExecutionContext c,CancellationToken ct) =>
        Read(FinancePermissions.CashRead,()=>persistence.ListMoneyAccountsAsync(c.CompanyId,ct),c,ct);
    public Task<Result<IReadOnlyList<FinancePostingPeriodView>>> ListPostingPeriodsAsync(IExecutionContext c,CancellationToken ct) =>
        Read(FinancePermissions.PeriodRead,()=>persistence.ListPostingPeriodsAsync(c.CompanyId,ct),c,ct);
    public Task<Result<IReadOnlyList<FinanceValuationPoolView>>> ListValuationPoolsAsync(IExecutionContext c,CancellationToken ct) =>
        Read(FinancePermissions.CostRead,()=>persistence.ListValuationPoolsAsync(c.CompanyId,ct),c,ct);
    public Task<Result<IReadOnlyList<FinanceStatementLineView>>> ListStatementLinesAsync(IExecutionContext c,CancellationToken ct) =>
        Read(FinancePermissions.StatementRead,()=>persistence.ListStatementLinesAsync(c.CompanyId,ct),c,ct);

    public async Task<Result<FinanceRiskView?>> GetCustomerRiskAsync(Guid partyPublicId,IExecutionContext c,CancellationToken ct)
    {
        if(!await Granted(FinancePermissions.RiskRead,c,ct)) return Denied<FinanceRiskView?>();
        return Result<FinanceRiskView?>.Success(await persistence.GetCustomerRiskAsync(c.CompanyId,partyPublicId,ct));
    }

    private async Task<Result<IReadOnlyList<T>>> Read<T>(
        string permission,Func<Task<IReadOnlyList<T>>> read,IExecutionContext c,CancellationToken ct)
    {
        if(!await Granted(permission,c,ct)) return Denied<IReadOnlyList<T>>();
        return Result<IReadOnlyList<T>>.Success(await read());
    }

    private Task<bool> Granted(string p,IExecutionContext c,CancellationToken ct) =>
        permissions.IsGrantedAsync(c.ActorId,c.CompanyId,p,ct);
    private static Result<T> Denied<T>() => Result<T>.Failure(
        new ApplicationError(ErrorCategory.Authorization,"finance.permission.denied","The required Finance permission is not granted."));
}

public sealed class FinanceCommandHandler(
    IPermissionEvaluator permissions,
    IFinancePersistence persistence)
{
    public Task<Result<FinanceMutationReceipt>> CreateMoneyAccountAsync(CreateFinanceMoneyAccountCommand x,IExecutionContext c,CancellationToken ct) =>
        With(x.Kind==FinanceMoneyAccountKind.Cash?FinancePermissions.CashManageAccount:FinancePermissions.BankManageAccount,
            ()=>persistence.CreateMoneyAccountAsync(x,c,ct),c,ct);

    public Task<Result<FinanceMutationReceipt>> PostCollectionAsync(PostCollectionCommand x,IExecutionContext c,CancellationToken ct) =>
        With(FinancePermissions.CollectionPost,()=>persistence.PostCollectionAsync(x,c,ct),c,ct);

    public Task<Result<FinanceMutationReceipt>> PostSupplierPaymentAsync(PostSupplierPaymentCommand x,IExecutionContext c,CancellationToken ct) =>
        With(FinancePermissions.PaymentPost,()=>persistence.PostSupplierPaymentAsync(x,c,ct),c,ct);

    public Task<Result<FinanceMutationReceipt>> PostRefundAsync(PostRefundCommand x,IExecutionContext c,CancellationToken ct) =>
        With(FinancePermissions.RefundPost,()=>persistence.PostRefundAsync(x,c,ct),c,ct);

    public Task<Result<FinanceMutationReceipt>> PostRoleNettingAsync(PostRoleNettingCommand x,IExecutionContext c,CancellationToken ct) =>
        With(FinancePermissions.RoleNettingCreate,()=>persistence.PostRoleNettingAsync(x,c,ct),c,ct);

    public Task<Result<FinanceMutationReceipt>> PostTreasuryTransferAsync(PostTreasuryTransferCommand x,IExecutionContext c,CancellationToken ct) =>
        With(FinancePermissions.TransferPost,()=>persistence.PostTreasuryTransferAsync(x,c,ct),c,ct);

    public Task<Result<FinanceMutationReceipt>> ReverseTransactionAsync(Guid id,string reason,string key,IExecutionContext c,CancellationToken ct) =>
        With(FinancePermissions.AccountRead,()=>persistence.ReverseTransactionAsync(id,reason,key,c,ct),c,ct);

    public Task<Result<FinanceMutationReceipt>> CreatePostingPeriodAsync(CreatePostingPeriodCommand x,IExecutionContext c,CancellationToken ct) =>
        With(FinancePermissions.PeriodFreeze,()=>persistence.CreatePostingPeriodAsync(x,c,ct),c,ct);

    public Task<Result<FinanceMutationReceipt>> ChangePostingPeriodStateAsync(ChangePostingPeriodStateCommand x,IExecutionContext c,CancellationToken ct)
    {
        var permission=x.State switch{
            FinancePostingPeriodState.Frozen=>FinancePermissions.PeriodFreeze,
            FinancePostingPeriodState.Closed=>FinancePermissions.PeriodClose,
            _=>FinancePermissions.PeriodReopen
        };
        return With(permission,()=>persistence.ChangePostingPeriodStateAsync(x,c,ct),c,ct);
    }

    public Task<Result<FinanceMutationReceipt>> SetCustomerRiskAsync(SetCustomerRiskCommand x,IExecutionContext c,CancellationToken ct) =>
        With(x.ManualHold?FinancePermissions.HoldManage:FinancePermissions.RiskManage,
            ()=>persistence.SetCustomerRiskAsync(x,c,ct),c,ct);

    public Task<Result<FinanceMutationReceipt>> ImportStatementAsync(ImportBankStatementCommand x,IExecutionContext c,CancellationToken ct) =>
        With(FinancePermissions.BankStatementImport,()=>persistence.ImportStatementAsync(x,c,ct),c,ct);

    public Task<Result<FinanceMutationReceipt>> ReconcileStatementAsync(ReconcileStatementCommand x,IExecutionContext c,CancellationToken ct) =>
        With(FinancePermissions.BankReconcile,()=>persistence.ReconcileStatementAsync(x,c,ct),c,ct);

    private async Task<Result<FinanceMutationReceipt>> With(
        string permission,Func<Task<Result<FinanceMutationReceipt>>> action,IExecutionContext c,CancellationToken ct)
    {
        if(!await permissions.IsGrantedAsync(c.ActorId,c.CompanyId,permission,ct))
            return Result<FinanceMutationReceipt>.Failure(
                new ApplicationError(ErrorCategory.Authorization,"finance.permission.denied","The required Finance permission is not granted."));
        return await action();
    }
}
