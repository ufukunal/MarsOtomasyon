using Mars.Api.Foundation.Errors;
using Mars.Application.Finance;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Results;
using Mars.Domain.Finance;

namespace Mars.Api.Finance;

public static class FinanceEndpoints
{
    public static void MapFinanceEndpoints(this WebApplication app)
    {
        var finance=app.MapGroup("/api/v1/finance").RequireAuthorization();

        finance.MapGet("/transactions",async(IExecutionContext c,FinanceQueryHandler h,CancellationToken ct)=>
            Map(await h.ListTransactionsAsync(c,ct),c)).WithName("ListFinanceTransactions");
        finance.MapGet("/account-balances",async(IExecutionContext c,FinanceQueryHandler h,CancellationToken ct)=>
            Map(await h.ListAccountBalancesAsync(c,ct),c)).WithName("ListFinanceAccountBalances");
        finance.MapGet("/money-accounts",async(IExecutionContext c,FinanceQueryHandler h,CancellationToken ct)=>
            Map(await h.ListMoneyAccountsAsync(c,ct),c)).WithName("ListFinanceMoneyAccounts");
        finance.MapGet("/periods",async(IExecutionContext c,FinanceQueryHandler h,CancellationToken ct)=>
            Map(await h.ListPostingPeriodsAsync(c,ct),c)).WithName("ListFinancePostingPeriods");
        finance.MapGet("/valuation-pools",async(IExecutionContext c,FinanceQueryHandler h,CancellationToken ct)=>
            Map(await h.ListValuationPoolsAsync(c,ct),c)).WithName("ListFinanceValuationPools");
        finance.MapGet("/statement-lines",async(IExecutionContext c,FinanceQueryHandler h,CancellationToken ct)=>
            Map(await h.ListStatementLinesAsync(c,ct),c)).WithName("ListFinanceStatementLines");
        finance.MapGet("/risk/{partyPublicId:guid}",async(Guid partyPublicId,IExecutionContext c,FinanceQueryHandler h,CancellationToken ct)=>
            Map(await h.GetCustomerRiskAsync(partyPublicId,c,ct),c)).WithName("GetFinanceCustomerRisk");

        finance.MapPost("/cash-accounts",async(CreateMoneyAccountRequest r,HttpRequest http,IExecutionContext c,FinanceCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/finance/cash-accounts",await h.CreateMoneyAccountAsync(
                new CreateFinanceMoneyAccountCommand(FinanceMoneyAccountKind.Cash,r.Code,r.Name,r.CurrencyCode,r.BranchId,null,r.OpeningBalance,r.PostingDate,Key(http)),c,ct),c))
            .WithName("CreateFinanceCashAccount");

        finance.MapPost("/bank-accounts",async(CreateMoneyAccountRequest r,HttpRequest http,IExecutionContext c,FinanceCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/finance/bank-accounts",await h.CreateMoneyAccountAsync(
                new CreateFinanceMoneyAccountCommand(FinanceMoneyAccountKind.Bank,r.Code,r.Name,r.CurrencyCode,r.BranchId,r.Iban,r.OpeningBalance,r.PostingDate,Key(http)),c,ct),c))
            .WithName("CreateFinanceBankAccount");

        finance.MapPost("/collections",async(PostCollectionRequest r,HttpRequest http,IExecutionContext c,FinanceCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/finance/transactions",await h.PostCollectionAsync(
                new PostCollectionCommand(r.CustomerPartyPublicId,r.TargetAccountKind,r.TargetAccountPublicId,r.Amount,r.CurrencyCode,
                    r.DocumentDate,r.PostingDate,r.Reason,Key(http)),c,ct),c))
            .WithName("PostFinanceCollection");

        finance.MapPost("/supplier-payments",async(PostSupplierPaymentRequest r,HttpRequest http,IExecutionContext c,FinanceCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/finance/transactions",await h.PostSupplierPaymentAsync(
                new PostSupplierPaymentCommand(r.SupplierPartyPublicId,r.SourceAccountKind,r.SourceAccountPublicId,r.Amount,r.CurrencyCode,
                    r.DocumentDate,r.PostingDate,r.Reason,Key(http)),c,ct),c))
            .WithName("PostFinanceSupplierPayment");

        finance.MapPost("/refunds",async(PostRefundRequest r,HttpRequest http,IExecutionContext c,FinanceCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/finance/transactions",await h.PostRefundAsync(
                new PostRefundCommand(r.PartyRole,r.PartyPublicId,r.AccountKind,r.AccountPublicId,r.Amount,r.CurrencyCode,
                    r.DocumentDate,r.PostingDate,r.Reason,Key(http)),c,ct),c))
            .WithName("PostFinanceRefund");

        finance.MapPost("/transfers",async(PostTreasuryTransferRequest r,HttpRequest http,IExecutionContext c,FinanceCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/finance/transactions",await h.PostTreasuryTransferAsync(
                new PostTreasuryTransferCommand(r.SourceKind,r.SourceAccountPublicId,r.TargetKind,r.TargetAccountPublicId,r.Amount,r.CurrencyCode,
                    r.DocumentDate,r.PostingDate,r.Reason,Key(http)),c,ct),c))
            .WithName("PostFinanceTreasuryTransfer");

        finance.MapPost("/transactions/{id:guid}/reverse",async(Guid id,ReasonRequest r,HttpRequest http,IExecutionContext c,FinanceCommandHandler h,CancellationToken ct)=>
            Map(await h.ReverseTransactionAsync(id,r.Reason,Key(http),c,ct),c)).WithName("ReverseFinanceTransaction");

        finance.MapPost("/periods",async(CreatePostingPeriodRequest r,HttpRequest http,IExecutionContext c,FinanceCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/finance/periods",await h.CreatePostingPeriodAsync(
                new CreatePostingPeriodCommand(r.StartDate,r.EndDate,Key(http)),c,ct),c))
            .WithName("CreateFinancePostingPeriod");

        finance.MapPost("/periods/{id:guid}/state",async(Guid id,ChangePostingPeriodStateRequest r,HttpRequest http,IExecutionContext c,FinanceCommandHandler h,CancellationToken ct)=>
            Map(await h.ChangePostingPeriodStateAsync(
                new ChangePostingPeriodStateCommand(id,r.Version,r.State,r.Reason,Key(http)),c,ct),c))
            .WithName("ChangeFinancePostingPeriodState");

        finance.MapPut("/risk/{partyPublicId:guid}",async(Guid partyPublicId,SetCustomerRiskRequest r,HttpRequest http,IExecutionContext c,FinanceCommandHandler h,CancellationToken ct)=>
            Map(await h.SetCustomerRiskAsync(new SetCustomerRiskCommand(
                partyPublicId,r.CreditLimit,r.ManualHold,r.HoldReason,Key(http)),c,ct),c))
            .WithName("SetFinanceCustomerRisk");

        finance.MapPost("/bank-statements",async(ImportBankStatementRequest r,HttpRequest http,IExecutionContext c,FinanceCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/finance/bank-statements",await h.ImportStatementAsync(
                new ImportBankStatementCommand(r.BankAccountPublicId,r.SourceName,r.SourceReference,
                    r.Lines.Select(x=>new ImportStatementLineInput(x.BookingDate,x.ValueDate,x.Amount,x.CurrencyCode,x.ExternalId,x.Description)).ToArray(),
                    Key(http)),c,ct),c))
            .WithName("ImportFinanceBankStatement");

        finance.MapPost("/reconciliation",async(ReconcileStatementRequest r,HttpRequest http,IExecutionContext c,FinanceCommandHandler h,CancellationToken ct)=>
            Created("/api/v1/finance/reconciliation",await h.ReconcileStatementAsync(
                new ReconcileStatementCommand(r.StatementLinePublicId,r.BankLedgerEntryPublicId,r.MatchedAmount,Key(http)),c,ct),c))
            .WithName("ReconcileFinanceStatement");
    }

    private static string Key(HttpRequest request)=>request.Headers["Idempotency-Key"].ToString();
    private static IResult Map<T>(Result<T> result,IExecutionContext c)=>
        result.IsFailure?ApplicationErrorHttpMapper.ToResult(result.Error!,c.CorrelationId.Value):Results.Ok(result.Value);
    private static IResult Created<T>(string basePath,Result<T> result,IExecutionContext c)=>
        result.IsFailure?ApplicationErrorHttpMapper.ToResult(result.Error!,c.CorrelationId.Value):
        result.Value is FinanceMutationReceipt x?Results.Created($"{basePath}/{x.PublicId:D}",result.Value):Results.Ok(result.Value);
}

public sealed record CreateMoneyAccountRequest(
    string Code,string Name,string CurrencyCode,Guid? BranchId,string? Iban,decimal OpeningBalance,DateOnly PostingDate);
public sealed record PostCollectionRequest(
    Guid CustomerPartyPublicId,FinanceMoneyAccountKind TargetAccountKind,Guid TargetAccountPublicId,decimal Amount,string CurrencyCode,
    DateOnly DocumentDate,DateOnly PostingDate,string? Reason);
public sealed record PostSupplierPaymentRequest(
    Guid SupplierPartyPublicId,FinanceMoneyAccountKind SourceAccountKind,Guid SourceAccountPublicId,decimal Amount,string CurrencyCode,
    DateOnly DocumentDate,DateOnly PostingDate,string? Reason);
public sealed record PostRefundRequest(
    FinancePartyRole PartyRole,Guid PartyPublicId,FinanceMoneyAccountKind AccountKind,Guid AccountPublicId,decimal Amount,string CurrencyCode,
    DateOnly DocumentDate,DateOnly PostingDate,string Reason);
public sealed record PostTreasuryTransferRequest(
    FinanceMoneyAccountKind SourceKind,Guid SourceAccountPublicId,FinanceMoneyAccountKind TargetKind,Guid TargetAccountPublicId,
    decimal Amount,string CurrencyCode,DateOnly DocumentDate,DateOnly PostingDate,string? Reason);
public sealed record ReasonRequest(string Reason);
public sealed record CreatePostingPeriodRequest(DateOnly StartDate,DateOnly EndDate);
public sealed record ChangePostingPeriodStateRequest(long Version,FinancePostingPeriodState State,string? Reason);
public sealed record SetCustomerRiskRequest(decimal? CreditLimit,bool ManualHold,string? HoldReason);
public sealed record ImportStatementLineRequest(DateOnly BookingDate,DateOnly? ValueDate,decimal Amount,string CurrencyCode,string? ExternalId,string? Description);
public sealed record ImportBankStatementRequest(Guid BankAccountPublicId,string SourceName,string? SourceReference,IReadOnlyList<ImportStatementLineRequest> Lines);
public sealed record ReconcileStatementRequest(Guid StatementLinePublicId,Guid BankLedgerEntryPublicId,decimal MatchedAmount);
