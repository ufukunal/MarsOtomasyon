using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using Mars.Application.Finance;
using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Outbox;
using Mars.Application.Foundation.Results;
using Mars.Domain.Finance;
using Mars.Domain.Parties;
using Mars.Domain.Products;
using Mars.Infrastructure.Persistence.Parties;
using Mars.Infrastructure.Persistence.Products;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Storage;
using Npgsql;

namespace Mars.Infrastructure.Persistence.Finance;

public sealed class EfFinancePersistence(
    MarsDbContext dbContext,
    IAuditWriter auditWriter,
    IIdempotencyStore idempotencyStore,
    IOutboxWriter outboxWriter)
    : IFinancePersistence, IFinanceValuationAuthority
{
    public async Task<IReadOnlyList<FinanceTransactionView>> ListTransactionsAsync(Guid companyId,CancellationToken ct)
    {
        var rows=await dbContext.Set<FinanceTransactionRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId).OrderByDescending(x=>x.Id).Take(500).ToArrayAsync(ct);
        var partyIds=rows.Where(x=>x.PartyId.HasValue).Select(x=>x.PartyId!.Value).Distinct().ToArray();
        var parties=partyIds.Length==0
            ? new Dictionary<long,PartyRecord>()
            : await dbContext.Set<PartyRecord>().AsNoTracking().Where(x=>x.CompanyId==companyId&&partyIds.Contains(x.Id))
                .ToDictionaryAsync(x=>x.Id,ct);
        var txIds=rows.Where(x=>x.ReversalOfTransactionId.HasValue).Select(x=>x.ReversalOfTransactionId!.Value).Distinct().ToArray();
        var originals=txIds.Length==0
            ? new Dictionary<long,Guid>()
            : await dbContext.Set<FinanceTransactionRecord>().AsNoTracking().Where(x=>x.CompanyId==companyId&&txIds.Contains(x.Id))
                .ToDictionaryAsync(x=>x.Id,x=>x.PublicId,ct);
        return rows.Select(x=>new FinanceTransactionView(
            x.PublicId,x.Kind.ToString().ToUpperInvariant(),x.State.ToString().ToUpperInvariant(),
            x.CurrencyCode,x.Amount,x.BaseAmount,
            x.PartyId.HasValue&&parties.TryGetValue(x.PartyId.Value,out var p)?p.PublicId:null,
            x.PartyRole?.ToString().ToUpperInvariant(),x.DocumentDate,x.PostingDate,x.SourceDocumentPublicId,
            x.ReversalOfTransactionId.HasValue?originals.GetValueOrDefault(x.ReversalOfTransactionId.Value):null,
            x.Reason,x.PostedAt)).ToArray();
    }

    public async Task<IReadOnlyList<FinanceAccountBalanceView>> ListAccountBalancesAsync(Guid companyId,CancellationToken ct)
    {
        var rows=await (
            from e in dbContext.Set<AccountLedgerEntryRecord>().AsNoTracking()
            join p in dbContext.Set<PartyRecord>().AsNoTracking() on new{e.PartyId,e.CompanyId} equals new{PartyId=p.Id,p.CompanyId}
            where e.CompanyId==companyId
            group e by new{p.PublicId,p.PartyCode,p.LegalName,e.PartyRole,e.CurrencyCode} into g
            select new{
                g.Key.PublicId,g.Key.PartyCode,g.Key.LegalName,g.Key.PartyRole,g.Key.CurrencyCode,
                Balance=g.Sum(x=>
                    x.PartyRole==FinancePartyRole.Customer
                        ? (x.Direction==FinanceLedgerDirection.Debit?x.Amount:-x.Amount)
                        : (x.Direction==FinanceLedgerDirection.Credit?x.Amount:-x.Amount))
            }).OrderBy(x=>x.PartyCode).ThenBy(x=>x.PartyRole).ToArrayAsync(ct);
        return rows.Select(x=>new FinanceAccountBalanceView(
            x.PublicId,x.PartyCode,x.LegalName,x.PartyRole.ToString().ToUpperInvariant(),x.CurrencyCode,x.Balance)).ToArray();
    }

    public async Task<IReadOnlyList<FinanceMoneyAccountView>> ListMoneyAccountsAsync(Guid companyId,CancellationToken ct)
    {
        var cash=await dbContext.Set<CashAccountRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId).OrderBy(x=>x.Code).ToArrayAsync(ct);
        var cashIds=cash.Select(x=>x.Id).ToArray();
        var cashBalances=cashIds.Length==0
            ? new Dictionary<long,decimal>()
            : await dbContext.Set<CashLedgerEntryRecord>().AsNoTracking().Where(x=>x.CompanyId==companyId&&cashIds.Contains(x.CashAccountId))
                .GroupBy(x=>x.CashAccountId).Select(g=>new{Id=g.Key,Balance=g.Sum(x=>x.Direction==FinanceMoneyDirection.In?x.Amount:-x.Amount)})
                .ToDictionaryAsync(x=>x.Id,x=>x.Balance,ct);

        var bank=await dbContext.Set<BankAccountRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId).OrderBy(x=>x.Code).ToArrayAsync(ct);
        var bankIds=bank.Select(x=>x.Id).ToArray();
        var bankBalances=bankIds.Length==0
            ? new Dictionary<long,decimal>()
            : await dbContext.Set<BankLedgerEntryRecord>().AsNoTracking().Where(x=>x.CompanyId==companyId&&bankIds.Contains(x.BankAccountId))
                .GroupBy(x=>x.BankAccountId).Select(g=>new{Id=g.Key,Balance=g.Sum(x=>x.Direction==FinanceMoneyDirection.In?x.Amount:-x.Amount)})
                .ToDictionaryAsync(x=>x.Id,x=>x.Balance,ct);

        return cash.Select(x=>new FinanceMoneyAccountView(
                x.PublicId,"CASH",x.Code,x.Name,x.CurrencyCode,x.State.ToString().ToUpperInvariant(),x.BranchId,null,
                cashBalances.GetValueOrDefault(x.Id)))
            .Concat(bank.Select(x=>new FinanceMoneyAccountView(
                x.PublicId,"BANK",x.Code,x.Name,x.CurrencyCode,x.State.ToString().ToUpperInvariant(),x.BranchId,MaskIban(x.Iban),
                bankBalances.GetValueOrDefault(x.Id))))
            .OrderBy(x=>x.Kind).ThenBy(x=>x.Code).ToArray();
    }

    public async Task<IReadOnlyList<FinancePostingPeriodView>> ListPostingPeriodsAsync(Guid companyId,CancellationToken ct) =>
        (await dbContext.Set<FinancePostingPeriodRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId).OrderByDescending(x=>x.StartDate).ToArrayAsync(ct))
        .Select(x=>new FinancePostingPeriodView(x.PublicId,x.StartDate,x.EndDate,x.State.ToString().ToUpperInvariant(),x.Version)).ToArray();

    public async Task<IReadOnlyList<FinanceValuationPoolView>> ListValuationPoolsAsync(Guid companyId,CancellationToken ct)
    {
        var pools=await dbContext.Set<InventoryValuationPoolRecord>().AsNoTracking().Where(x=>x.CompanyId==companyId).ToArrayAsync(ct);
        var ids=pools.Select(x=>x.Id).ToArray();
        var effects=ids.Length==0?Array.Empty<PoolEffect>():await dbContext.Set<InventoryValuationEntryRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&ids.Contains(x.PoolId))
            .GroupBy(x=>x.PoolId).Select(g=>new PoolEffect(g.Key,g.Sum(x=>x.InventoryQuantityEffect),g.Sum(x=>x.InventoryBaseValueEffect)))
            .ToArrayAsync(ct);
        var effectMap=effects.ToDictionary(x=>x.PoolId);
        var products=await MapProducts(companyId,pools.Select(x=>x.ProductId),ct);
        var variants=await MapVariants(companyId,pools.Where(x=>x.VariantId.HasValue).Select(x=>x.VariantId!.Value),ct);
        var uoms=await MapUoms(companyId,pools.Select(x=>x.BaseUomId),ct);
        return pools.Select(x=>{
            var e=effectMap.GetValueOrDefault(x.Id,new PoolEffect(x.Id,0m,0m));
            return new FinanceValuationPoolView(
                products[x.ProductId].PublicId,x.VariantId.HasValue?variants[x.VariantId.Value].PublicId:null,uoms[x.BaseUomId].PublicId,
                x.CurrencyCode,e.Quantity,e.Quantity>0m?e.Value:0m,e.Quantity>0m?FinanceAmount.RoundValue(e.Value/e.Quantity):null);
        }).ToArray();
    }

    public async Task<IReadOnlyList<FinanceStatementLineView>> ListStatementLinesAsync(Guid companyId,CancellationToken ct)
    {
        var rows=await (
            from s in dbContext.Set<StatementLineRecord>().AsNoTracking()
            join b in dbContext.Set<StatementImportBatchRecord>().AsNoTracking() on new{s.BatchId,s.CompanyId} equals new{BatchId=b.Id,b.CompanyId}
            join a in dbContext.Set<BankAccountRecord>().AsNoTracking() on new{s.BankAccountId,s.CompanyId} equals new{BankAccountId=a.Id,a.CompanyId}
            where s.CompanyId==companyId
            orderby s.BookingDate descending,s.Id descending
            select new{Line=s,BatchPublicId=b.PublicId,BankAccountPublicId=a.PublicId}).Take(500).ToArrayAsync(ct);
        var lineIds=rows.Select(x=>x.Line.Id).ToArray();
        var matched=lineIds.Length==0?new Dictionary<long,decimal>():await dbContext.Set<ReconciliationMatchRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&lineIds.Contains(x.StatementLineId)&&x.State==FinanceReconciliationState.Active)
            .GroupBy(x=>x.StatementLineId).Select(g=>new{Id=g.Key,Amount=g.Sum(x=>x.MatchedAmount)})
            .ToDictionaryAsync(x=>x.Id,x=>x.Amount,ct);
        return rows.Select(x=>new FinanceStatementLineView(
            x.Line.PublicId,x.BatchPublicId,x.BankAccountPublicId,x.Line.BookingDate,x.Line.ValueDate,x.Line.Amount,x.Line.CurrencyCode,
            x.Line.ExternalId,x.Line.Fingerprint,x.Line.Description,x.Line.State.ToString().ToUpperInvariant(),
            matched.GetValueOrDefault(x.Line.Id))).ToArray();
    }

    public async Task<FinanceRiskView?> GetCustomerRiskAsync(Guid companyId,Guid partyPublicId,CancellationToken ct)
    {
        var party=await ResolveParty(companyId,partyPublicId,PartyRoleType.Customer,ct);
        if(party is null)return null;
        var control=await dbContext.Set<CustomerRiskControlRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PartyId==party.Id,ct);
        var balance=await PartyBalance(companyId,party.Id,FinancePartyRole.Customer,FinanceAmount.CurrentBaseCurrency,ct);
        var exposure=Math.Max(balance,0m);
        var credit=Math.Max(-balance,0m);
        var limitExceeded=control?.CreditLimit is decimal limit&&exposure>limit;
        return new FinanceRiskView(party.PublicId,control?.CreditLimit,control?.ManualHold??false,control?.HoldReason,
            exposure,credit,exposure,limitExceeded);
    }

    public Task<Result<FinanceMutationReceipt>> CreateMoneyAccountAsync(CreateFinanceMoneyAccountCommand command,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.money_account.create",command.OperationKey,"MoneyAccountCreated","MoneyAccount",c,async innerCt=>{
            if(command.Kind is not (FinanceMoneyAccountKind.Cash or FinanceMoneyAccountKind.Bank)||
               string.IsNullOrWhiteSpace(command.Code)||string.IsNullOrWhiteSpace(command.Name)||
               !ValidTry(command.CurrencyCode)||command.OpeningBalance<0m)
                return Validation("finance.money_account.invalid","Kind, code, name, TRY currency and non-negative opening balance are required.");
            if(command.OpeningBalance>0m){
                var gate=await GatePeriod(c.CompanyId,command.PostingDate,innerCt);
                if(gate is not null)return Result<FinanceMutationReceipt>.Failure(gate);
            }
            var now=DateTimeOffset.UtcNow;
            Guid publicId=Guid.NewGuid();
            long accountId;
            if(command.Kind==FinanceMoneyAccountKind.Cash){
                var a=new CashAccountRecord{PublicId=publicId,CompanyId=c.CompanyId,BranchId=command.BranchId,
                    Code=command.Code.Trim(),Name=command.Name.Trim(),CurrencyCode="TRY",State=FinanceAccountState.Active,
                    Version=1,CreatedAt=now};
                dbContext.Add(a); await dbContext.SaveChangesAsync(innerCt); accountId=a.Id;
            }else{
                var a=new BankAccountRecord{PublicId=publicId,CompanyId=c.CompanyId,BranchId=command.BranchId,
                    Code=command.Code.Trim(),Name=command.Name.Trim(),CurrencyCode="TRY",Iban=Normalize(command.Iban),
                    State=FinanceAccountState.Active,Version=1,CreatedAt=now};
                dbContext.Add(a); await dbContext.SaveChangesAsync(innerCt); accountId=a.Id;
            }
            if(command.OpeningBalance>0m){
                var amount=FinanceAmount.RoundTry(command.OpeningBalance);
                var tx=NewTransaction(c,FinanceTransactionKind.OpeningBalance,amount,command.PostingDate,command.PostingDate,
                    null,null,"Finance","MoneyAccount",publicId,null);
                dbContext.Add(tx); await dbContext.SaveChangesAsync(innerCt);
                AddMoneyEntry(command.Kind,accountId,tx.Id,c.CompanyId,FinanceMoneyDirection.In,amount,now);
            }
            return Success(publicId,"ACTIVE",1,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> PostCollectionAsync(PostCollectionCommand command,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.collection.post",command.OperationKey,"CollectionPosted","FinanceTransaction",c,async innerCt=>{
            var basic=ValidatePosting(command.Amount,command.CurrencyCode,command.PostingDate);
            if(basic is not null)return Result<FinanceMutationReceipt>.Failure(basic);
            var gate=await GatePeriod(c.CompanyId,command.PostingDate,innerCt); if(gate is not null)return Result<FinanceMutationReceipt>.Failure(gate);
            var party=await ResolveParty(c.CompanyId,command.CustomerPartyPublicId,PartyRoleType.Customer,innerCt);
            if(party is null)return Business("finance.customer.not_eligible","Party must be ACTIVE with an ACTIVE CUSTOMER role.");
            var account=await ResolveMoneyAccount(c.CompanyId,command.TargetAccountKind,command.TargetAccountPublicId,innerCt);
            if(account is null||account.Currency!="TRY")return Business("finance.money_account.not_eligible","Target account must be ACTIVE and use TRY.");
            var amount=FinanceAmount.RoundTry(command.Amount); var now=DateTimeOffset.UtcNow;
            var tx=NewTransaction(c,FinanceTransactionKind.Collection,amount,command.DocumentDate,command.PostingDate,
                party.Id,FinancePartyRole.Customer,"Finance","Collection",null,command.Reason);
            dbContext.Add(tx); await dbContext.SaveChangesAsync(innerCt);
            AddAccountEntry(tx.Id,c.CompanyId,party.Id,FinancePartyRole.Customer,FinanceLedgerDirection.Credit,amount,null,now);
            AddMoneyEntry(command.TargetAccountKind,account.Id,tx.Id,c.CompanyId,FinanceMoneyDirection.In,amount,now);
            return Success(tx.PublicId,"POSTED",tx.Version,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> PostSupplierPaymentAsync(PostSupplierPaymentCommand command,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.payment.post",command.OperationKey,"SupplierPaymentPosted","FinanceTransaction",c,async innerCt=>{
            var basic=ValidatePosting(command.Amount,command.CurrencyCode,command.PostingDate);
            if(basic is not null)return Result<FinanceMutationReceipt>.Failure(basic);
            var gate=await GatePeriod(c.CompanyId,command.PostingDate,innerCt); if(gate is not null)return Result<FinanceMutationReceipt>.Failure(gate);
            var party=await ResolveParty(c.CompanyId,command.SupplierPartyPublicId,PartyRoleType.Supplier,innerCt);
            if(party is null)return Business("finance.supplier.not_eligible","Party must be ACTIVE with an ACTIVE SUPPLIER role.");
            var account=await ResolveMoneyAccount(c.CompanyId,command.SourceAccountKind,command.SourceAccountPublicId,innerCt);
            if(account is null||account.Currency!="TRY")return Business("finance.money_account.not_eligible","Source account must be ACTIVE and use TRY.");
            var amount=FinanceAmount.RoundTry(command.Amount);
            if(await MoneyBalance(c.CompanyId,command.SourceAccountKind,account.Id,innerCt)<amount)
                return Business("finance.money_account.insufficient","Negative Cash/Bank book balance is blocked while no overdraft policy authority exists.");
            var now=DateTimeOffset.UtcNow;
            var tx=NewTransaction(c,FinanceTransactionKind.SupplierPayment,amount,command.DocumentDate,command.PostingDate,
                party.Id,FinancePartyRole.Supplier,"Finance","SupplierPayment",null,command.Reason);
            dbContext.Add(tx); await dbContext.SaveChangesAsync(innerCt);
            AddAccountEntry(tx.Id,c.CompanyId,party.Id,FinancePartyRole.Supplier,FinanceLedgerDirection.Debit,amount,null,now);
            AddMoneyEntry(command.SourceAccountKind,account.Id,tx.Id,c.CompanyId,FinanceMoneyDirection.Out,amount,now);
            return Success(tx.PublicId,"POSTED",tx.Version,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> PostRefundAsync(PostRefundCommand command,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.refund.post",command.OperationKey,"RefundPosted","FinanceTransaction",c,async innerCt=>{
            var basic=ValidatePosting(command.Amount,command.CurrencyCode,command.PostingDate);
            if(basic is not null||string.IsNullOrWhiteSpace(command.Reason))
                return basic is null?Validation("finance.refund.reason","Refund reason is required."):Result<FinanceMutationReceipt>.Failure(basic);
            var gate=await GatePeriod(c.CompanyId,command.PostingDate,innerCt); if(gate is not null)return Result<FinanceMutationReceipt>.Failure(gate);
            var role=command.PartyRole==FinancePartyRole.Customer?PartyRoleType.Customer:PartyRoleType.Supplier;
            var party=await ResolveParty(c.CompanyId,command.PartyPublicId,role,innerCt);
            if(party is null)return Business("finance.refund.party_not_eligible","Party role is not eligible.");
            var account=await ResolveMoneyAccount(c.CompanyId,command.AccountKind,command.AccountPublicId,innerCt);
            if(account is null||account.Currency!="TRY")return Business("finance.money_account.not_eligible","Account must be ACTIVE and use TRY.");
            var amount=FinanceAmount.RoundTry(command.Amount);
            var balance=await PartyBalance(c.CompanyId,party.Id,command.PartyRole,"TRY",innerCt);
            if(balance>=0m||amount>Math.Abs(balance))
                return Business("finance.refund.entitlement","Refund cannot exceed the eligible role credit/advance balance.");
            if(command.PartyRole==FinancePartyRole.Customer&&await MoneyBalance(c.CompanyId,command.AccountKind,account.Id,innerCt)<amount)
                return Business("finance.money_account.insufficient","Customer refund would create a negative Cash/Bank book balance.");
            var kind=command.PartyRole==FinancePartyRole.Customer?FinanceTransactionKind.CustomerRefund:FinanceTransactionKind.SupplierRefund;
            var moneyDirection=command.PartyRole==FinancePartyRole.Customer?FinanceMoneyDirection.Out:FinanceMoneyDirection.In;
            var ledgerDirection=command.PartyRole==FinancePartyRole.Customer?FinanceLedgerDirection.Debit:FinanceLedgerDirection.Credit;
            var now=DateTimeOffset.UtcNow;
            var tx=NewTransaction(c,kind,amount,command.DocumentDate,command.PostingDate,party.Id,command.PartyRole,
                "Finance","Refund",null,command.Reason);
            dbContext.Add(tx); await dbContext.SaveChangesAsync(innerCt);
            AddAccountEntry(tx.Id,c.CompanyId,party.Id,command.PartyRole,ledgerDirection,amount,null,now);
            AddMoneyEntry(command.AccountKind,account.Id,tx.Id,c.CompanyId,moneyDirection,amount,now);
            return Success(tx.PublicId,"POSTED",1,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> PostRoleNettingAsync(PostRoleNettingCommand command,IExecutionContext c,CancellationToken ct) =>
        Task.FromResult(Business("finance.role_netting.approval_required",
            "Role netting remains fail-closed until its mandatory Foundation approval intent is recorded and approved."));

    public Task<Result<FinanceMutationReceipt>> PostTreasuryTransferAsync(PostTreasuryTransferCommand command,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.transfer.post",command.OperationKey,"TreasuryTransferPosted","FinanceTransaction",c,async innerCt=>{
            var basic=ValidatePosting(command.Amount,command.CurrencyCode,command.PostingDate);
            if(basic is not null)return Result<FinanceMutationReceipt>.Failure(basic);
            if(command.SourceKind==command.TargetKind&&command.SourceAccountPublicId==command.TargetAccountPublicId)
                return Validation("finance.transfer.same_account","Source and target accounts must differ.");
            var gate=await GatePeriod(c.CompanyId,command.PostingDate,innerCt); if(gate is not null)return Result<FinanceMutationReceipt>.Failure(gate);
            var source=await ResolveMoneyAccount(c.CompanyId,command.SourceKind,command.SourceAccountPublicId,innerCt);
            var target=await ResolveMoneyAccount(c.CompanyId,command.TargetKind,command.TargetAccountPublicId,innerCt);
            if(source is null||target is null||source.Currency!="TRY"||target.Currency!="TRY")
                return Business("finance.transfer.account","Both accounts must be ACTIVE TRY accounts in the same company.");
            var amount=FinanceAmount.RoundTry(command.Amount);
            if(await MoneyBalance(c.CompanyId,command.SourceKind,source.Id,innerCt)<amount)
                return Business("finance.money_account.insufficient","Transfer would create a negative source Cash/Bank book balance.");
            var now=DateTimeOffset.UtcNow;
            var tx=NewTransaction(c,FinanceTransactionKind.TreasuryTransfer,amount,command.DocumentDate,command.PostingDate,
                null,null,"Finance","TreasuryTransfer",null,command.Reason);
            dbContext.Add(tx); await dbContext.SaveChangesAsync(innerCt);
            AddMoneyEntry(command.SourceKind,source.Id,tx.Id,c.CompanyId,FinanceMoneyDirection.Out,amount,now);
            AddMoneyEntry(command.TargetKind,target.Id,tx.Id,c.CompanyId,FinanceMoneyDirection.In,amount,now);
            return Success(tx.PublicId,"POSTED",1,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> ReverseTransactionAsync(Guid transactionPublicId,string reason,string operationKey,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.transaction.reverse",operationKey,"FinanceTransactionReversed","FinanceTransaction",c,async innerCt=>{
            if(transactionPublicId==Guid.Empty||string.IsNullOrWhiteSpace(reason))
                return Validation("finance.reverse.invalid","Transaction and reason are required.");
            var original=await dbContext.Set<FinanceTransactionRecord>()
                .FromSqlInterpolated($@"SELECT * FROM finance.transactions WHERE company_id={c.CompanyId} AND public_id={transactionPublicId} FOR UPDATE")
                .SingleOrDefaultAsync(innerCt);
            if(original is null)return NotFound("finance.transaction.not_found","Finance transaction was not found.");
            if(original.State!=FinanceTransactionState.Posted)return Business("finance.reverse.state","Only POSTED transaction can be reversed.");
            if(original.Kind is FinanceTransactionKind.GoodsReceiptProvisional or FinanceTransactionKind.DispatchCarryingValue or
               FinanceTransactionKind.SalesInvoiceCogs or FinanceTransactionKind.SupplierInvoiceLateCost or
               FinanceTransactionKind.StockCountAdjustment or FinanceTransactionKind.ScrapWriteOff)
                return Business("finance.reverse.source_owned","Source-integrated valuation transaction must be reversed through its owning module workflow.");
            var gate=await GatePeriod(c.CompanyId,DateOnly.FromDateTime(DateTime.UtcNow),innerCt);
            if(gate is not null)return Result<FinanceMutationReceipt>.Failure(gate);
            if(await dbContext.Set<ReconciliationMatchRecord>().AsNoTracking().AnyAsync(r=>r.CompanyId==c.CompanyId&&r.State==FinanceReconciliationState.Active&&
               dbContext.Set<BankLedgerEntryRecord>().Any(be=>be.Id==r.BankLedgerEntryId&&be.TransactionId==original.Id),innerCt))
                return Business("finance.reverse.reconciled","Reconciled Bank movement must be unreconciled/reviewed before reversal.");
            var now=DateTimeOffset.UtcNow;
            var reversal=NewTransaction(c,FinanceTransactionKind.Reversal,original.Amount,original.DocumentDate,DateOnly.FromDateTime(now.UtcDateTime),
                original.PartyId,original.PartyRole,"Finance","FinanceTransactionReversal",original.PublicId,reason);
            reversal.ReversalOfTransactionId=original.Id;
            dbContext.Add(reversal); await dbContext.SaveChangesAsync(innerCt);
            foreach(var e in await dbContext.Set<AccountLedgerEntryRecord>().AsNoTracking().Where(x=>x.CompanyId==c.CompanyId&&x.TransactionId==original.Id).ToArrayAsync(innerCt))
                AddAccountEntry(reversal.Id,c.CompanyId,e.PartyId,e.PartyRole,Opposite(e.Direction),e.Amount,e.DueDate,now);
            foreach(var e in await dbContext.Set<CashLedgerEntryRecord>().AsNoTracking().Where(x=>x.CompanyId==c.CompanyId&&x.TransactionId==original.Id).ToArrayAsync(innerCt))
                AddMoneyEntry(FinanceMoneyAccountKind.Cash,e.CashAccountId,reversal.Id,c.CompanyId,Opposite(e.Direction),e.Amount,now);
            foreach(var e in await dbContext.Set<BankLedgerEntryRecord>().AsNoTracking().Where(x=>x.CompanyId==c.CompanyId&&x.TransactionId==original.Id).ToArrayAsync(innerCt))
                AddMoneyEntry(FinanceMoneyAccountKind.Bank,e.BankAccountId,reversal.Id,c.CompanyId,Opposite(e.Direction),e.Amount,now);
            original.State=FinanceTransactionState.Reversed; original.ReversedAt=now; original.Version++;
            return Success(reversal.PublicId,"POSTED",1,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> CreatePostingPeriodAsync(CreatePostingPeriodCommand command,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.period.create",command.OperationKey,"PostingPeriodCreated","PostingPeriod",c,async innerCt=>{
            if(command.EndDate<command.StartDate)return Validation("finance.period.range","Posting period end must be on or after start.");
            if(await dbContext.Set<FinancePostingPeriodRecord>().AnyAsync(x=>x.CompanyId==c.CompanyId&&x.StartDate<=command.EndDate&&x.EndDate>=command.StartDate,innerCt))
                return Business("finance.period.overlap","Posting periods cannot overlap.");
            var row=new FinancePostingPeriodRecord{PublicId=Guid.NewGuid(),CompanyId=c.CompanyId,StartDate=command.StartDate,EndDate=command.EndDate,
                State=FinancePostingPeriodState.Open,Version=1,CreatorActorId=c.ActorId,CreatedAt=DateTimeOffset.UtcNow};
            dbContext.Add(row);
            return Success(row.PublicId,"OPEN",1,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> ChangePostingPeriodStateAsync(ChangePostingPeriodStateCommand command,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.period.state",command.OperationKey,"PostingPeriodStateChanged","PostingPeriod",c,async innerCt=>{
            var p=await dbContext.Set<FinancePostingPeriodRecord>().SingleOrDefaultAsync(x=>x.CompanyId==c.CompanyId&&x.PublicId==command.PeriodPublicId,innerCt);
            if(p is null)return NotFound("finance.period.not_found","Posting period was not found.");
            if(p.Version!=command.ExpectedVersion)return Conflict("finance.period.stale","Posting period version is stale.");
            if(command.State==p.State)return Business("finance.period.same_state","Posting period is already in the requested state.");
            if(p.State==FinancePostingPeriodState.Closed&&command.State!=FinancePostingPeriodState.Open)
                return Business("finance.period.reopen_target","CLOSED period can only be explicitly reopened to OPEN.");
            p.State=command.State;p.Version++;p.ChangedAt=DateTimeOffset.UtcNow;
            return Success(p.PublicId,p.State.ToString().ToUpperInvariant(),p.Version,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> SetCustomerRiskAsync(SetCustomerRiskCommand command,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.risk.set",command.OperationKey,"CustomerRiskChanged","CustomerRiskControl",c,async innerCt=>{
            if(command.CreditLimit<0m)return Validation("finance.risk.limit","Credit limit cannot be negative.");
            if(command.ManualHold&&string.IsNullOrWhiteSpace(command.HoldReason))
                return Validation("finance.risk.hold_reason","Manual hold requires a reason.");
            var party=await ResolveParty(c.CompanyId,command.PartyPublicId,PartyRoleType.Customer,innerCt);
            if(party is null)return Business("finance.customer.not_eligible","Party must be ACTIVE with an ACTIVE CUSTOMER role.");
            var row=await dbContext.Set<CustomerRiskControlRecord>().SingleOrDefaultAsync(x=>x.CompanyId==c.CompanyId&&x.PartyId==party.Id,innerCt);
            var now=DateTimeOffset.UtcNow;
            if(row is null){
                row=new CustomerRiskControlRecord{PublicId=Guid.NewGuid(),CompanyId=c.CompanyId,PartyId=party.Id,
                    CreditLimit=command.CreditLimit.HasValue?FinanceAmount.RoundTry(command.CreditLimit.Value):null,
                    ManualHold=command.ManualHold,HoldReason=Normalize(command.HoldReason),Version=1,UpdatedByActorId=c.ActorId,UpdatedAt=now};
                dbContext.Add(row);
            }else{
                row.CreditLimit=command.CreditLimit.HasValue?FinanceAmount.RoundTry(command.CreditLimit.Value):null;
                row.ManualHold=command.ManualHold;row.HoldReason=Normalize(command.HoldReason);row.Version++;row.UpdatedByActorId=c.ActorId;row.UpdatedAt=now;
            }
            return Success(row.PublicId,command.ManualHold?"ON_HOLD":"ACTIVE",row.Version,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> ImportStatementAsync(ImportBankStatementCommand command,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.statement.import",command.OperationKey,"BankStatementImported","StatementImportBatch",c,async innerCt=>{
            if(command.BankAccountPublicId==Guid.Empty||string.IsNullOrWhiteSpace(command.SourceName)||command.Lines.Count==0)
                return Validation("finance.statement.invalid","Bank account, source and statement lines are required.");
            var bank=await dbContext.Set<BankAccountRecord>().SingleOrDefaultAsync(x=>x.CompanyId==c.CompanyId&&x.PublicId==command.BankAccountPublicId&&x.State==FinanceAccountState.Active,innerCt);
            if(bank is null)return NotFound("finance.bank.not_found","Active Bank account was not found.");
            if(bank.CurrencyCode!="TRY"||command.Lines.Any(x=>!ValidTry(x.CurrencyCode)||x.Amount==0m))
                return Business("finance.statement.currency","Current authoritative statement normalization accepts non-zero TRY rows only.");
            var batch=new StatementImportBatchRecord{PublicId=Guid.NewGuid(),CompanyId=c.CompanyId,BankAccountId=bank.Id,
                SourceName=command.SourceName.Trim(),SourceReference=Normalize(command.SourceReference),CreatorActorId=c.ActorId,ImportedAt=DateTimeOffset.UtcNow};
            dbContext.Add(batch);await dbContext.SaveChangesAsync(innerCt);
            foreach(var line in command.Lines){
                var fingerprint=Fingerprint(bank.PublicId,line);
                dbContext.Add(new StatementLineRecord{PublicId=Guid.NewGuid(),BatchId=batch.Id,CompanyId=c.CompanyId,BankAccountId=bank.Id,
                    BookingDate=line.BookingDate,ValueDate=line.ValueDate,Amount=FinanceAmount.RoundTry(line.Amount),CurrencyCode="TRY",
                    ExternalId=Normalize(line.ExternalId),Fingerprint=fingerprint,Description=Normalize(line.Description),
                    State=FinanceStatementLineState.Unmatched,CreatedAt=DateTimeOffset.UtcNow});
            }
            return Success(batch.PublicId,"IMPORTED",1,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> ReconcileStatementAsync(ReconcileStatementCommand command,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.statement.reconcile",command.OperationKey,"BankStatementReconciled","StatementLine",c,async innerCt=>{
            if(command.MatchedAmount<=0m)return Validation("finance.reconcile.amount","Matched amount must be positive.");
            var line=await dbContext.Set<StatementLineRecord>()
                .FromSqlInterpolated($@"SELECT * FROM finance.statement_lines WHERE company_id={c.CompanyId} AND public_id={command.StatementLinePublicId} FOR UPDATE")
                .SingleOrDefaultAsync(innerCt);
            var bank=await dbContext.Set<BankLedgerEntryRecord>().AsNoTracking()
                .SingleOrDefaultAsync(x=>x.CompanyId==c.CompanyId&&x.PublicId==command.BankLedgerEntryPublicId,innerCt);
            if(line is null||bank is null)return NotFound("finance.reconcile.source_not_found","Statement line or Bank Ledger entry was not found.");
            if(line.BankAccountId!=bank.BankAccountId)return Business("finance.reconcile.bank_mismatch","Statement and Bank Ledger entry must belong to the same Bank account.");
            var matched=await dbContext.Set<ReconciliationMatchRecord>().AsNoTracking()
                .Where(x=>x.CompanyId==c.CompanyId&&x.StatementLineId==line.Id&&x.State==FinanceReconciliationState.Active)
                .SumAsync(x=>(decimal?)x.MatchedAmount,innerCt)??0m;
            var requested=FinanceAmount.RoundTry(command.MatchedAmount);
            if(matched+requested>Math.Abs(line.Amount)||requested>bank.Amount)
                return Business("finance.reconcile.overmatch","Reconciliation cannot exceed statement or ledger amount.");
            var row=new ReconciliationMatchRecord{PublicId=Guid.NewGuid(),CompanyId=c.CompanyId,StatementLineId=line.Id,
                BankLedgerEntryId=bank.Id,MatchedAmount=requested,State=FinanceReconciliationState.Active,ActorId=c.ActorId,CreatedAt=DateTimeOffset.UtcNow};
            dbContext.Add(row);
            var total=matched+requested;
            line.State=total==Math.Abs(line.Amount)?FinanceStatementLineState.Matched:FinanceStatementLineState.PartiallyMatched;
            return Success(row.PublicId,line.State.ToString().ToUpperInvariant(),1,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> PostGoodsReceiptAsync(FinanceGoodsReceiptValuationCommand command,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.valuation.receipt.post",command.OperationKey,"GoodsReceiptValued","GoodsReceipt",c,async innerCt=>{
            if(command.GoodsReceiptPublicId==Guid.Empty||command.Lines.Count==0||command.Lines.Any(x=>x.BaseQuantity<=0m||x.ProvisionalBaseValue<0m))
                return Validation("finance.valuation.receipt.invalid","Receipt valuation requires positive quantities and non-negative provisional values.");
            var gate=await GatePeriod(c.CompanyId,command.PostingDate,innerCt);if(gate is not null)return Result<FinanceMutationReceipt>.Failure(gate);
            if(await SourceTransactionExists(c.CompanyId,FinanceTransactionKind.GoodsReceiptProvisional,command.GoodsReceiptPublicId,innerCt))
                return Conflict("finance.valuation.receipt.duplicate","Goods Receipt valuation is already posted.");
            var total=FinanceAmount.RoundValue(command.Lines.Sum(x=>x.ProvisionalBaseValue));
            var tx=NewTransaction(c,FinanceTransactionKind.GoodsReceiptProvisional,total,command.PostingDate,command.PostingDate,
                null,null,"Purchasing","GoodsReceipt",command.GoodsReceiptPublicId,null);
            dbContext.Add(tx);await dbContext.SaveChangesAsync(innerCt);
            foreach(var line in command.Lines){
                var pool=await GetOrCreatePool(c.CompanyId,line.ProductPublicId,line.VariantPublicId,line.UomPublicId,innerCt);
                if(pool.IsFailure)return Result<FinanceMutationReceipt>.Failure(pool.Error!);
                var value=FinanceAmount.RoundValue(line.ProvisionalBaseValue);
                dbContext.Add(new InventoryValuationEntryRecord{PublicId=Guid.NewGuid(),TransactionId=tx.Id,CompanyId=c.CompanyId,PoolId=pool.Value!.Id,
                    Kind=FinanceValuationKind.GoodsReceiptProvisional,InventoryQuantityEffect=line.BaseQuantity,InventoryBaseValueEffect=value,ExpenseBaseValueEffect=0m,
                    UnitBaseValue=line.BaseQuantity==0m?null:FinanceAmount.RoundValue(value/line.BaseQuantity),InventoryMovementPublicId=line.InventoryMovementPublicId,
                    SourceModule="Purchasing",SourceEntityType="GoodsReceipt",SourceDocumentPublicId=command.GoodsReceiptPublicId,SourceLinePublicId=line.GoodsReceiptLinePublicId,
                    PostedAt=DateTimeOffset.UtcNow});
            }
            return Success(tx.PublicId,"POSTED",1,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> ReverseGoodsReceiptAsync(Guid goodsReceiptPublicId,DateOnly postingDate,string operationKey,IExecutionContext c,CancellationToken ct) =>
        ReverseValuationSource("finance.valuation.receipt.reverse",goodsReceiptPublicId,FinanceTransactionKind.GoodsReceiptProvisional,
            "Purchasing","GoodsReceipt",postingDate,operationKey,c,ct);

    public Task<Result<FinanceMutationReceipt>> PostDispatchAsync(FinanceDispatchValuationCommand command,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.valuation.dispatch.post",command.OperationKey,"DispatchValued","Dispatch",c,async innerCt=>{
            if(command.DispatchPublicId==Guid.Empty||command.Lines.Count==0||command.Lines.Any(x=>x.BaseQuantity<=0m))
                return Validation("finance.valuation.dispatch.invalid","Dispatch valuation requires positive source quantities.");
            var gate=await GatePeriod(c.CompanyId,command.PostingDate,innerCt);if(gate is not null)return Result<FinanceMutationReceipt>.Failure(gate);
            if(await SourceTransactionExists(c.CompanyId,FinanceTransactionKind.DispatchCarryingValue,command.DispatchPublicId,innerCt))
                return Conflict("finance.valuation.dispatch.duplicate","Dispatch valuation is already posted.");
            var prepared=new List<(FinanceDispatchValuationLine Line,InventoryValuationPoolRecord Pool,decimal Value)>();
            foreach(var line in command.Lines){
                var pool=await GetExistingPool(c.CompanyId,line.ProductPublicId,line.VariantPublicId,line.UomPublicId,innerCt);
                if(pool is null)return Business("finance.valuation.pool_missing","Dispatch cannot value stock without an existing carrying-value pool.");
                var balance=await PoolBalance(c.CompanyId,pool.Id,innerCt);
                if(balance.Quantity<=0m||balance.Quantity<line.BaseQuantity||balance.Value<0m)
                    return Business("finance.valuation.insufficient","Dispatch quantity exceeds valued on-hand quantity or carrying value is invalid.");
                var unit=FinanceAmount.RoundValue(balance.Value/balance.Quantity);
                prepared.Add((line,pool,FinanceAmount.RoundValue(unit*line.BaseQuantity)));
            }
            var total=FinanceAmount.RoundValue(prepared.Sum(x=>x.Value));
            var tx=NewTransaction(c,FinanceTransactionKind.DispatchCarryingValue,total,command.PostingDate,command.PostingDate,
                null,null,"Sales","Dispatch",command.DispatchPublicId,null);
            dbContext.Add(tx);await dbContext.SaveChangesAsync(innerCt);
            foreach(var x in prepared){
                var now=DateTimeOffset.UtcNow;
                dbContext.Add(new InventoryValuationEntryRecord{PublicId=Guid.NewGuid(),TransactionId=tx.Id,CompanyId=c.CompanyId,PoolId=x.Pool.Id,
                    Kind=FinanceValuationKind.DispatchCarryingValue,InventoryQuantityEffect=-x.Line.BaseQuantity,InventoryBaseValueEffect=-x.Value,ExpenseBaseValueEffect=0m,
                    UnitBaseValue=FinanceAmount.RoundValue(x.Value/x.Line.BaseQuantity),InventoryMovementPublicId=x.Line.InventoryMovementPublicId,
                    SourceModule="Sales",SourceEntityType="Dispatch",SourceDocumentPublicId=command.DispatchPublicId,SourceLinePublicId=x.Line.DispatchLinePublicId,PostedAt=now});
                dbContext.Add(new DispatchCostBridgeEntryRecord{PublicId=Guid.NewGuid(),TransactionId=tx.Id,CompanyId=c.CompanyId,PoolId=x.Pool.Id,
                    DispatchPublicId=command.DispatchPublicId,DispatchLinePublicId=x.Line.DispatchLinePublicId,PhysicalSourcePublicId=x.Line.PhysicalSourcePublicId,
                    InventoryMovementPublicId=x.Line.InventoryMovementPublicId,BaseQuantity=x.Line.BaseQuantity,BaseValue=x.Value,PostedAt=now});
            }
            return Success(tx.PublicId,"POSTED",1,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> ReverseDispatchAsync(
        FinanceDispatchReversalCommand command,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.valuation.dispatch.reverse",command.OperationKey,"DispatchValuationReversed","Dispatch",c,async innerCt=>{
            if(command.DispatchPublicId==Guid.Empty||command.Lines.Count==0||
               command.Lines.Any(x=>x.OriginalInventoryMovementPublicId==Guid.Empty||x.ReversalInventoryMovementPublicId==Guid.Empty)||
               command.Lines.Select(x=>x.OriginalInventoryMovementPublicId).Distinct().Count()!=command.Lines.Count||
               command.Lines.Select(x=>x.ReversalInventoryMovementPublicId).Distinct().Count()!=command.Lines.Count)
                return Validation("finance.dispatch.reverse.invalid","Dispatch reversal requires unique original and reversal Inventory movement lineage.");

            var gate=await GatePeriod(c.CompanyId,command.PostingDate,innerCt);
            if(gate is not null)return Result<FinanceMutationReceipt>.Failure(gate);

            var original=await dbContext.Set<FinanceTransactionRecord>()
                .FromSqlInterpolated($@"SELECT * FROM finance.transactions WHERE company_id={c.CompanyId} AND kind='DispatchCarryingValue' AND source_document_public_id={command.DispatchPublicId} FOR UPDATE")
                .SingleOrDefaultAsync(innerCt);
            if(original is null)return NotFound("finance.valuation.original_not_found","Original Dispatch valuation transaction was not found.");
            if(original.State!=FinanceTransactionState.Posted)return Business("finance.valuation.reverse_state","Original Dispatch valuation is not POSTED.");

            var bridges=await dbContext.Set<DispatchCostBridgeEntryRecord>().AsNoTracking()
                .Where(x=>x.CompanyId==c.CompanyId&&x.TransactionId==original.Id&&x.ReversalOfBridgeEntryId==null)
                .OrderBy(x=>x.Id).ToArrayAsync(innerCt);
            var bridgeIds=bridges.Select(x=>x.Id).ToArray();
            var bridgeConsumptions=await dbContext.Set<DispatchCostBridgeConsumptionRecord>().AsNoTracking()
                .Where(x=>x.CompanyId==c.CompanyId&&bridgeIds.Contains(x.BridgeEntryId)).ToArrayAsync(innerCt);
            if(bridgeConsumptions.GroupBy(x=>x.BridgeEntryId).Any(g=>g.Sum(x=>x.BaseQuantity)>0m))
                return Business("finance.dispatch.reverse.cogs_consumed","Dispatch cost bridge has net Sales Invoice COGS consumption.");

            var movementMap=command.Lines.ToDictionary(x=>x.OriginalInventoryMovementPublicId,x=>x.ReversalInventoryMovementPublicId);
            if(bridges.Any(x=>!movementMap.ContainsKey(x.InventoryMovementPublicId))||
               movementMap.Keys.Any(x=>bridges.All(b=>b.InventoryMovementPublicId!=x)))
                return Business("finance.dispatch.reverse.lineage","Dispatch reversal movement lineage must exactly cover original Finance bridge effects.");

            var entries=await dbContext.Set<InventoryValuationEntryRecord>().AsNoTracking()
                .Where(x=>x.CompanyId==c.CompanyId&&x.TransactionId==original.Id).ToArrayAsync(innerCt);
            var tx=NewTransaction(c,FinanceTransactionKind.Reversal,original.Amount,command.PostingDate,command.PostingDate,
                null,null,"Sales","DispatchReversal",command.DispatchPublicId,null);
            tx.ReversalOfTransactionId=original.Id;
            dbContext.Add(tx);
            await dbContext.SaveChangesAsync(innerCt);
            var now=DateTimeOffset.UtcNow;

            foreach(var e in entries)
            {
                Guid? reversalMovement=null;
                if(e.InventoryMovementPublicId.HasValue&&movementMap.TryGetValue(e.InventoryMovementPublicId.Value,out var mapped))
                    reversalMovement=mapped;
                dbContext.Add(new InventoryValuationEntryRecord{
                    PublicId=Guid.NewGuid(),TransactionId=tx.Id,CompanyId=c.CompanyId,PoolId=e.PoolId,
                    Kind=FinanceValuationKind.Reversal,InventoryQuantityEffect=-e.InventoryQuantityEffect,
                    InventoryBaseValueEffect=-e.InventoryBaseValueEffect,ExpenseBaseValueEffect=-e.ExpenseBaseValueEffect,
                    UnitBaseValue=e.UnitBaseValue,InventoryMovementPublicId=reversalMovement,
                    SourceModule="Sales",SourceEntityType="DispatchReversal",SourceDocumentPublicId=command.DispatchPublicId,
                    SourceLinePublicId=e.SourceLinePublicId,ReversalOfValuationEntryId=e.Id,PostedAt=now});
            }

            foreach(var e in bridges)
                dbContext.Add(new DispatchCostBridgeEntryRecord{
                    PublicId=Guid.NewGuid(),TransactionId=tx.Id,CompanyId=c.CompanyId,PoolId=e.PoolId,
                    DispatchPublicId=e.DispatchPublicId,DispatchLinePublicId=e.DispatchLinePublicId,
                    PhysicalSourcePublicId=e.PhysicalSourcePublicId,InventoryMovementPublicId=movementMap[e.InventoryMovementPublicId],
                    BaseQuantity=-e.BaseQuantity,BaseValue=-e.BaseValue,ReversalOfBridgeEntryId=e.Id,PostedAt=now});

            original.State=FinanceTransactionState.Reversed;
            original.ReversedAt=now;
            original.Version++;
            return Success(tx.PublicId,"POSTED",1,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> PostSalesInvoiceAsync(
        FinanceSalesInvoicePostCommand command,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.sales_invoice.post",command.OperationKey,"SalesInvoicePosted","SalesInvoice",c,async innerCt=>{
            if(command.SalesInvoicePublicId==Guid.Empty||command.CustomerPartyPublicId==Guid.Empty||
               command.GrossAmount<0m||command.DueDate<command.DocumentDate||!ValidTry(command.CurrencyCode)||
               command.DispatchLines.Any(x=>x.SalesInvoiceLinePublicId==Guid.Empty||x.DispatchPublicId==Guid.Empty||
                   x.DispatchLinePublicId==Guid.Empty||x.BaseQuantity<=0m))
                return Validation("finance.sales_invoice.invalid","Sales Invoice Finance posting snapshot is invalid.");

            var gate=await GatePeriod(c.CompanyId,command.PostingDate,innerCt);
            if(gate is not null)return Result<FinanceMutationReceipt>.Failure(gate);
            if(await SourceTransactionExists(c.CompanyId,FinanceTransactionKind.SalesInvoice,command.SalesInvoicePublicId,innerCt))
                return Conflict("finance.sales_invoice.duplicate","Sales Invoice financial effects are already posted.");

            var customer=await ResolveParty(c.CompanyId,command.CustomerPartyPublicId,PartyRoleType.Customer,innerCt);
            if(customer is null)return Business("finance.customer.not_eligible","Customer must be ACTIVE with an ACTIVE CUSTOMER role.");

            var amount=FinanceAmount.RoundTry(command.GrossAmount);
            var tx=NewTransaction(c,FinanceTransactionKind.SalesInvoice,amount,command.DocumentDate,command.PostingDate,
                customer.Id,FinancePartyRole.Customer,"Sales","SalesInvoice",command.SalesInvoicePublicId,null);
            dbContext.Add(tx);
            await dbContext.SaveChangesAsync(innerCt);
            var now=DateTimeOffset.UtcNow;
            if(amount>0m)
                AddAccountEntry(tx.Id,c.CompanyId,customer.Id,FinancePartyRole.Customer,FinanceLedgerDirection.Debit,amount,command.DueDate,now);

            foreach(var line in command.DispatchLines)
            {
                var bridges=await dbContext.Set<DispatchCostBridgeEntryRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==c.CompanyId&&x.DispatchPublicId==line.DispatchPublicId&&
                        x.DispatchLinePublicId==line.DispatchLinePublicId&&x.ReversalOfBridgeEntryId==null)
                    .OrderBy(x=>x.Id).ToArrayAsync(innerCt);
                if(bridges.Length==0)
                    return Business("finance.sales_invoice.bridge_missing","Dispatch-sourced Invoice line has no Finance cost bridge.");

                var bridgeIds=bridges.Select(x=>x.Id).ToArray();
                var prior=await dbContext.Set<DispatchCostBridgeConsumptionRecord>().AsNoTracking()
                    .Where(x=>x.CompanyId==c.CompanyId&&bridgeIds.Contains(x.BridgeEntryId)).ToArrayAsync(innerCt);
                var usedQty=prior.GroupBy(x=>x.BridgeEntryId).ToDictionary(g=>g.Key,g=>g.Sum(x=>x.BaseQuantity));
                var usedValue=prior.GroupBy(x=>x.BridgeEntryId).ToDictionary(g=>g.Key,g=>g.Sum(x=>x.BaseValue));
                var remaining=line.BaseQuantity;

                foreach(var bridge in bridges)
                {
                    var availableQty=bridge.BaseQuantity-usedQty.GetValueOrDefault(bridge.Id);
                    if(availableQty<=0m)continue;
                    var take=Math.Min(remaining,availableQty);
                    if(take<=0m)break;
                    var availableValue=bridge.BaseValue-usedValue.GetValueOrDefault(bridge.Id);
                    var value=take==availableQty
                        ? availableValue
                        : FinanceAmount.RoundValue(bridge.BaseValue/bridge.BaseQuantity*take);
                    if(value<0m)return Business("finance.sales_invoice.bridge_value","Dispatch bridge carrying value is inconsistent.");

                    dbContext.Add(new DispatchCostBridgeConsumptionRecord{
                        PublicId=Guid.NewGuid(),TransactionId=tx.Id,CompanyId=c.CompanyId,BridgeEntryId=bridge.Id,
                        SalesInvoicePublicId=command.SalesInvoicePublicId,SalesInvoiceLinePublicId=line.SalesInvoiceLinePublicId,
                        BaseQuantity=take,BaseValue=value,PostedAt=now});
                    dbContext.Add(new InventoryValuationEntryRecord{
                        PublicId=Guid.NewGuid(),TransactionId=tx.Id,CompanyId=c.CompanyId,PoolId=bridge.PoolId,
                        Kind=FinanceValuationKind.SalesInvoiceCogs,InventoryQuantityEffect=0m,InventoryBaseValueEffect=0m,
                        ExpenseBaseValueEffect=value,UnitBaseValue=take>0m?FinanceAmount.RoundValue(value/take):null,
                        SourceModule="Sales",SourceEntityType="SalesInvoice",SourceDocumentPublicId=command.SalesInvoicePublicId,
                        SourceLinePublicId=line.SalesInvoiceLinePublicId,PostedAt=now});
                    remaining-=take;
                    if(remaining<=0m)break;
                }

                if(remaining>0m)
                    return Business("finance.sales_invoice.bridge_insufficient","Dispatch cost bridge quantity is insufficient for the Invoice line.");
            }

            return Success(tx.PublicId,"POSTED",1,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> ReverseSalesInvoiceAsync(
        Guid salesInvoicePublicId,DateOnly postingDate,string operationKey,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.sales_invoice.reverse",operationKey,"SalesInvoiceReversed","SalesInvoice",c,async innerCt=>{
            var gate=await GatePeriod(c.CompanyId,postingDate,innerCt);
            if(gate is not null)return Result<FinanceMutationReceipt>.Failure(gate);
            var original=await dbContext.Set<FinanceTransactionRecord>()
                .FromSqlInterpolated($@"SELECT * FROM finance.transactions WHERE company_id={c.CompanyId} AND kind='SalesInvoice' AND source_document_public_id={salesInvoicePublicId} FOR UPDATE")
                .SingleOrDefaultAsync(innerCt);
            if(original is null)return NotFound("finance.sales_invoice.not_posted","Sales Invoice financial posting was not found.");
            if(original.State!=FinanceTransactionState.Posted)return Business("finance.sales_invoice.reverse_state","Sales Invoice financial posting is not POSTED.");

            var reversal=NewTransaction(c,FinanceTransactionKind.Reversal,original.Amount,postingDate,postingDate,
                original.PartyId,original.PartyRole,"Sales","SalesInvoiceReversal",salesInvoicePublicId,null);
            reversal.ReversalOfTransactionId=original.Id;
            dbContext.Add(reversal);
            await dbContext.SaveChangesAsync(innerCt);
            var now=DateTimeOffset.UtcNow;

            foreach(var e in await dbContext.Set<AccountLedgerEntryRecord>().AsNoTracking()
                        .Where(x=>x.CompanyId==c.CompanyId&&x.TransactionId==original.Id).ToArrayAsync(innerCt))
                AddAccountEntry(reversal.Id,c.CompanyId,e.PartyId,e.PartyRole,Opposite(e.Direction),e.Amount,e.DueDate,now);

            var consumptions=await dbContext.Set<DispatchCostBridgeConsumptionRecord>().AsNoTracking()
                .Where(x=>x.CompanyId==c.CompanyId&&x.TransactionId==original.Id&&x.ReversalOfConsumptionId==null)
                .ToArrayAsync(innerCt);
            foreach(var e in consumptions)
                dbContext.Add(new DispatchCostBridgeConsumptionRecord{
                    PublicId=Guid.NewGuid(),TransactionId=reversal.Id,CompanyId=c.CompanyId,BridgeEntryId=e.BridgeEntryId,
                    SalesInvoicePublicId=e.SalesInvoicePublicId,SalesInvoiceLinePublicId=e.SalesInvoiceLinePublicId,
                    BaseQuantity=-e.BaseQuantity,BaseValue=-e.BaseValue,ReversalOfConsumptionId=e.Id,PostedAt=now});

            var cogs=await dbContext.Set<InventoryValuationEntryRecord>().AsNoTracking()
                .Where(x=>x.CompanyId==c.CompanyId&&x.TransactionId==original.Id&&x.Kind==FinanceValuationKind.SalesInvoiceCogs)
                .ToArrayAsync(innerCt);
            foreach(var e in cogs)
                dbContext.Add(new InventoryValuationEntryRecord{
                    PublicId=Guid.NewGuid(),TransactionId=reversal.Id,CompanyId=c.CompanyId,PoolId=e.PoolId,
                    Kind=FinanceValuationKind.Reversal,InventoryQuantityEffect=0m,InventoryBaseValueEffect=0m,
                    ExpenseBaseValueEffect=-e.ExpenseBaseValueEffect,UnitBaseValue=e.UnitBaseValue,
                    SourceModule="Sales",SourceEntityType="SalesInvoiceReversal",SourceDocumentPublicId=salesInvoicePublicId,
                    SourceLinePublicId=e.SourceLinePublicId,ReversalOfValuationEntryId=e.Id,PostedAt=now});

            original.State=FinanceTransactionState.Reversed;
            original.ReversedAt=now;
            original.Version++;
            return Success(reversal.PublicId,"POSTED",1,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> PostSupplierInvoiceAsync(
        FinanceSupplierInvoicePostCommand command,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.supplier_invoice.post",command.OperationKey,"SupplierInvoicePosted","SupplierInvoice",c,async innerCt=>{
            if(command.SupplierInvoicePublicId==Guid.Empty||command.SupplierPartyPublicId==Guid.Empty||
               command.GrossAmount<0m||command.DueDate<command.DocumentDate||!ValidTry(command.CurrencyCode))
                return Validation("finance.supplier_invoice.invalid","Supplier Invoice Finance posting snapshot is invalid.");

            var gate=await GatePeriod(c.CompanyId,command.PostingDate,innerCt);
            if(gate is not null)return Result<FinanceMutationReceipt>.Failure(gate);
            if(await SourceTransactionExists(c.CompanyId,FinanceTransactionKind.SupplierInvoice,command.SupplierInvoicePublicId,innerCt))
                return Conflict("finance.supplier_invoice.duplicate","Supplier Invoice financial effects are already posted.");

            var supplier=await ResolveParty(c.CompanyId,command.SupplierPartyPublicId,PartyRoleType.Supplier,innerCt);
            if(supplier is null)return Business("finance.supplier.not_eligible","Supplier must be ACTIVE with an ACTIVE SUPPLIER role.");

            var amount=FinanceAmount.RoundTry(command.GrossAmount);
            var tx=NewTransaction(c,FinanceTransactionKind.SupplierInvoice,amount,command.DocumentDate,command.PostingDate,
                supplier.Id,FinancePartyRole.Supplier,"Purchasing","SupplierInvoice",command.SupplierInvoicePublicId,null);
            dbContext.Add(tx);
            await dbContext.SaveChangesAsync(innerCt);
            if(amount>0m)
                AddAccountEntry(tx.Id,c.CompanyId,supplier.Id,FinancePartyRole.Supplier,FinanceLedgerDirection.Credit,
                    amount,command.DueDate,DateTimeOffset.UtcNow);
            return Success(tx.PublicId,"POSTED",1,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> ReverseSupplierInvoiceAsync(
        Guid supplierInvoicePublicId,DateOnly postingDate,string operationKey,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.supplier_invoice.reverse",operationKey,"SupplierInvoiceReversed","SupplierInvoice",c,async innerCt=>{
            var gate=await GatePeriod(c.CompanyId,postingDate,innerCt);
            if(gate is not null)return Result<FinanceMutationReceipt>.Failure(gate);
            var original=await dbContext.Set<FinanceTransactionRecord>()
                .FromSqlInterpolated($@"SELECT * FROM finance.transactions WHERE company_id={c.CompanyId} AND kind='SupplierInvoice' AND source_document_public_id={supplierInvoicePublicId} FOR UPDATE")
                .SingleOrDefaultAsync(innerCt);
            if(original is null)return NotFound("finance.supplier_invoice.not_posted","Supplier Invoice financial posting was not found.");
            if(original.State!=FinanceTransactionState.Posted)return Business("finance.supplier_invoice.reverse_state","Supplier Invoice financial posting is not POSTED.");

            var reversal=NewTransaction(c,FinanceTransactionKind.Reversal,original.Amount,postingDate,postingDate,
                original.PartyId,original.PartyRole,"Purchasing","SupplierInvoiceReversal",supplierInvoicePublicId,null);
            reversal.ReversalOfTransactionId=original.Id;
            dbContext.Add(reversal);
            await dbContext.SaveChangesAsync(innerCt);
            foreach(var e in await dbContext.Set<AccountLedgerEntryRecord>().AsNoTracking()
                        .Where(x=>x.CompanyId==c.CompanyId&&x.TransactionId==original.Id).ToArrayAsync(innerCt))
                AddAccountEntry(reversal.Id,c.CompanyId,e.PartyId,e.PartyRole,Opposite(e.Direction),e.Amount,e.DueDate,DateTimeOffset.UtcNow);

            original.State=FinanceTransactionState.Reversed;
            original.ReversedAt=DateTimeOffset.UtcNow;
            original.Version++;
            return Success(reversal.PublicId,"POSTED",1,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> PostCountAdjustmentAsync(FinanceCountValuationCommand command,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.valuation.count.post",command.OperationKey,"StockCountValued","StockCount",c,async innerCt=>{
            if(command.CountPublicId==Guid.Empty||command.Lines.Count==0||command.Lines.Any(x=>x.BaseQuantityEffect==0m))
                return Validation("finance.valuation.count.invalid","Count valuation requires non-zero quantity effects.");
            var gate=await GatePeriod(c.CompanyId,command.PostingDate,innerCt);if(gate is not null)return Result<FinanceMutationReceipt>.Failure(gate);
            if(await SourceTransactionExists(c.CompanyId,FinanceTransactionKind.StockCountAdjustment,command.CountPublicId,innerCt))
                return Conflict("finance.valuation.count.duplicate","Stock Count valuation is already posted.");
            var prepared=new List<(FinanceCountValuationLine Line,InventoryValuationPoolRecord Pool,decimal Value,decimal Unit)>();
            foreach(var line in command.Lines){
                InventoryValuationPoolRecord? pool;
                if(line.BaseQuantityEffect>0m){
                    var existing=await GetExistingPool(c.CompanyId,line.ProductPublicId,line.VariantPublicId,line.UomPublicId,innerCt);
                    decimal unit;
                    if(existing is not null){
                        var bal=await PoolBalance(c.CompanyId,existing.Id,innerCt);
                        unit=bal.Quantity>0m&&bal.Value>=0m?FinanceAmount.RoundValue(bal.Value/bal.Quantity):0m;
                    }else unit=0m;
                    if(unit<=0m){
                        if(!line.ExplicitUnitBaseValue.HasValue||line.ExplicitUnitBaseValue.Value<=0m)
                            return Business("finance.count.positive_valuation_required","Positive Count requires current moving average or explicit approved unit valuation.");
                        unit=FinanceAmount.RoundValue(line.ExplicitUnitBaseValue.Value);
                    }
                    var ensured=existing is null?await GetOrCreatePool(c.CompanyId,line.ProductPublicId,line.VariantPublicId,line.UomPublicId,innerCt)
                        :Result<InventoryValuationPoolRecord>.Success(existing);
                    if(ensured.IsFailure)return Result<FinanceMutationReceipt>.Failure(ensured.Error!);
                    pool=ensured.Value!;
                    prepared.Add((line,pool,FinanceAmount.RoundValue(unit*line.BaseQuantityEffect),unit));
                }else{
                    pool=await GetExistingPool(c.CompanyId,line.ProductPublicId,line.VariantPublicId,line.UomPublicId,innerCt);
                    if(pool is null)return Business("finance.valuation.pool_missing","Negative Count requires existing carrying-value pool.");
                    var bal=await PoolBalance(c.CompanyId,pool.Id,innerCt);
                    var q=Math.Abs(line.BaseQuantityEffect);
                    if(bal.Quantity<=0m||bal.Quantity<q)return Business("finance.valuation.insufficient","Negative Count exceeds valued on-hand quantity.");
                    var unit=FinanceAmount.RoundValue(bal.Value/bal.Quantity);
                    prepared.Add((line,pool,-FinanceAmount.RoundValue(unit*q),unit));
                }
            }
            var amount=FinanceAmount.RoundValue(prepared.Sum(x=>Math.Abs(x.Value)));
            var tx=NewTransaction(c,FinanceTransactionKind.StockCountAdjustment,amount,command.PostingDate,command.PostingDate,
                null,null,"Warehouse","StockCount",command.CountPublicId,null);
            dbContext.Add(tx);await dbContext.SaveChangesAsync(innerCt);
            foreach(var x in prepared){
                dbContext.Add(new InventoryValuationEntryRecord{PublicId=Guid.NewGuid(),TransactionId=tx.Id,CompanyId=c.CompanyId,PoolId=x.Pool.Id,
                    Kind=FinanceValuationKind.StockCountAdjustment,InventoryQuantityEffect=x.Line.BaseQuantityEffect,InventoryBaseValueEffect=x.Value,
                    ExpenseBaseValueEffect=x.Line.BaseQuantityEffect<0m?Math.Abs(x.Value):-x.Value,UnitBaseValue=x.Unit,
                    InventoryMovementPublicId=x.Line.InventoryMovementPublicId,SourceModule="Warehouse",SourceEntityType="StockCount",
                    SourceDocumentPublicId=command.CountPublicId,SourceLinePublicId=x.Line.CountLinePublicId,PostedAt=DateTimeOffset.UtcNow});
            }
            return Success(tx.PublicId,"POSTED",1,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> ReverseCountAdjustmentAsync(Guid countPublicId,DateOnly postingDate,string operationKey,IExecutionContext c,CancellationToken ct) =>
        ReverseValuationSource("finance.valuation.count.reverse",countPublicId,FinanceTransactionKind.StockCountAdjustment,
            "Warehouse","StockCount",postingDate,operationKey,c,ct);

    public Task<Result<FinanceMutationReceipt>> PostScrapAsync(FinanceScrapValuationCommand command,IExecutionContext c,CancellationToken ct) =>
        Mutate("finance.valuation.scrap.post",command.OperationKey,"ScrapValued","Scrap",c,async innerCt=>{
            if(command.ScrapPublicId==Guid.Empty||command.BaseQuantity<=0m)
                return Validation("finance.valuation.scrap.invalid","Scrap valuation requires positive quantity.");
            var gate=await GatePeriod(c.CompanyId,command.PostingDate,innerCt);if(gate is not null)return Result<FinanceMutationReceipt>.Failure(gate);
            if(await SourceTransactionExists(c.CompanyId,FinanceTransactionKind.ScrapWriteOff,command.ScrapPublicId,innerCt))
                return Conflict("finance.valuation.scrap.duplicate","Scrap valuation is already posted.");
            var pool=await GetExistingPool(c.CompanyId,command.ProductPublicId,command.VariantPublicId,command.UomPublicId,innerCt);
            if(pool is null)return Business("finance.valuation.pool_missing","Scrap requires existing carrying-value pool.");
            var bal=await PoolBalance(c.CompanyId,pool.Id,innerCt);
            if(bal.Quantity<=0m||bal.Quantity<command.BaseQuantity)return Business("finance.valuation.insufficient","Scrap exceeds valued on-hand quantity.");
            var unit=FinanceAmount.RoundValue(bal.Value/bal.Quantity);
            var value=FinanceAmount.RoundValue(unit*command.BaseQuantity);
            var tx=NewTransaction(c,FinanceTransactionKind.ScrapWriteOff,value,command.PostingDate,command.PostingDate,
                null,null,"Warehouse","Scrap",command.ScrapPublicId,null);
            dbContext.Add(tx);await dbContext.SaveChangesAsync(innerCt);
            dbContext.Add(new InventoryValuationEntryRecord{PublicId=Guid.NewGuid(),TransactionId=tx.Id,CompanyId=c.CompanyId,PoolId=pool.Id,
                Kind=FinanceValuationKind.ScrapWriteOff,InventoryQuantityEffect=-command.BaseQuantity,InventoryBaseValueEffect=-value,
                ExpenseBaseValueEffect=value,UnitBaseValue=unit,InventoryMovementPublicId=command.InventoryMovementPublicId,
                SourceModule="Warehouse",SourceEntityType="Scrap",SourceDocumentPublicId=command.ScrapPublicId,PostedAt=DateTimeOffset.UtcNow});
            return Success(tx.PublicId,"POSTED",1,c);
        },ct);

    public Task<Result<FinanceMutationReceipt>> ReverseScrapAsync(Guid scrapPublicId,DateOnly postingDate,string operationKey,IExecutionContext c,CancellationToken ct) =>
        ReverseValuationSource("finance.valuation.scrap.reverse",scrapPublicId,FinanceTransactionKind.ScrapWriteOff,
            "Warehouse","Scrap",postingDate,operationKey,c,ct);

    private Task<Result<FinanceMutationReceipt>> ReverseValuationSource(
        string scope,Guid sourceId,FinanceTransactionKind originalKind,string module,string entity,DateOnly postingDate,
        string operationKey,IExecutionContext c,CancellationToken ct) =>
        Mutate(scope,operationKey,entity+"ValuationReversed",entity,c,async innerCt=>{
            var gate=await GatePeriod(c.CompanyId,postingDate,innerCt);if(gate is not null)return Result<FinanceMutationReceipt>.Failure(gate);
            var original=await dbContext.Set<FinanceTransactionRecord>()
                .FromSqlInterpolated($@"SELECT * FROM finance.transactions WHERE company_id={c.CompanyId} AND kind={originalKind.ToString()} AND source_document_public_id={sourceId} FOR UPDATE")
                .SingleOrDefaultAsync(innerCt);
            if(original is null)return NotFound("finance.valuation.original_not_found","Original valuation transaction was not found.");
            if(original.State!=FinanceTransactionState.Posted)return Business("finance.valuation.reverse_state","Original valuation is not POSTED.");
            var entries=await dbContext.Set<InventoryValuationEntryRecord>().AsNoTracking()
                .Where(x=>x.CompanyId==c.CompanyId&&x.TransactionId==original.Id).ToArrayAsync(innerCt);
            var tx=NewTransaction(c,FinanceTransactionKind.Reversal,original.Amount,postingDate,postingDate,null,null,module,entity+"Reversal",sourceId,null);
            tx.ReversalOfTransactionId=original.Id;dbContext.Add(tx);await dbContext.SaveChangesAsync(innerCt);
            foreach(var e in entries)
                dbContext.Add(new InventoryValuationEntryRecord{PublicId=Guid.NewGuid(),TransactionId=tx.Id,CompanyId=c.CompanyId,PoolId=e.PoolId,
                    Kind=FinanceValuationKind.Reversal,InventoryQuantityEffect=-e.InventoryQuantityEffect,InventoryBaseValueEffect=-e.InventoryBaseValueEffect,
                    ExpenseBaseValueEffect=-e.ExpenseBaseValueEffect,UnitBaseValue=e.UnitBaseValue,SourceModule=module,SourceEntityType=entity+"Reversal",
                    SourceDocumentPublicId=sourceId,SourceLinePublicId=e.SourceLinePublicId,ReversalOfValuationEntryId=e.Id,PostedAt=DateTimeOffset.UtcNow});
            original.State=FinanceTransactionState.Reversed;original.ReversedAt=DateTimeOffset.UtcNow;original.Version++;
            return Success(tx.PublicId,"POSTED",1,c);
        },ct);

    private async Task<Result<InventoryValuationPoolRecord>> GetOrCreatePool(
        Guid companyId,Guid productPublicId,Guid? variantPublicId,Guid enteredUomPublicId,CancellationToken ct)
    {
        var existing=await GetExistingPool(companyId,productPublicId,variantPublicId,enteredUomPublicId,ct);
        if(existing is not null)return Result<InventoryValuationPoolRecord>.Success(existing);

        var product=await dbContext.Set<ProductRecord>().SingleOrDefaultAsync(
            x=>x.CompanyId==companyId&&x.PublicId==productPublicId&&x.Stockable,ct);
        if(product is null)return Fail<InventoryValuationPoolRecord>("finance.valuation.product","Stockable Product was not found.");

        long? variantId=null;
        if(variantPublicId.HasValue){
            var variant=await dbContext.Set<ProductVariantRecord>().SingleOrDefaultAsync(
                x=>x.CompanyId==companyId&&x.PublicId==variantPublicId&&x.ProductId==product.Id,ct);
            if(variant is null)return Fail<InventoryValuationPoolRecord>("finance.valuation.variant","Variant was not found.");
            variantId=variant.Id;
        }

        var enteredUom=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking().SingleOrDefaultAsync(
            x=>x.CompanyId==companyId&&x.PublicId==enteredUomPublicId,ct);
        if(enteredUom is null)return Fail<InventoryValuationPoolRecord>("finance.valuation.uom","Entered UOM was not found.");
        if(!await dbContext.Set<ProductUomRecord>().AsNoTracking().AnyAsync(
            x=>x.CompanyId==companyId&&x.ProductId==product.Id&&x.UomId==enteredUom.Id&&
               x.State==ProductMasterRecordState.Active&&(x.VariantId==null||x.VariantId==variantId),ct))
            return Fail<InventoryValuationPoolRecord>("finance.valuation.uom_assignment","Entered UOM is not active for the Product/Variant.");

        var baseAssignment=await dbContext.Set<ProductUomRecord>().AsNoTracking().SingleOrDefaultAsync(
            x=>x.CompanyId==companyId&&x.ProductId==product.Id&&x.VariantId==null&&
               x.Role==ProductUomRole.Base&&x.State==ProductMasterRecordState.Active,ct);
        if(baseAssignment is null)return Fail<InventoryValuationPoolRecord>("finance.valuation.base_uom","Product has no active base UOM.");

        var pool=new InventoryValuationPoolRecord{
            PublicId=Guid.NewGuid(),CompanyId=companyId,ProductId=product.Id,VariantId=variantId,
            BaseUomId=baseAssignment.UomId,CurrencyCode="TRY",CreatedAt=DateTimeOffset.UtcNow};
        dbContext.Add(pool);
        await dbContext.SaveChangesAsync(ct);
        return Result<InventoryValuationPoolRecord>.Success(pool);
    }

    private async Task<InventoryValuationPoolRecord?> GetExistingPool(
        Guid companyId,Guid productPublicId,Guid? variantPublicId,Guid enteredUomPublicId,CancellationToken ct)
    {
        var product=await dbContext.Set<ProductRecord>().AsNoTracking().SingleOrDefaultAsync(
            x=>x.CompanyId==companyId&&x.PublicId==productPublicId&&x.Stockable,ct);
        if(product is null)return null;

        long? variantId=null;
        if(variantPublicId.HasValue){
            var variant=await dbContext.Set<ProductVariantRecord>().AsNoTracking().SingleOrDefaultAsync(
                x=>x.CompanyId==companyId&&x.PublicId==variantPublicId&&x.ProductId==product.Id,ct);
            if(variant is null)return null;
            variantId=variant.Id;
        }

        var enteredUom=await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking().SingleOrDefaultAsync(
            x=>x.CompanyId==companyId&&x.PublicId==enteredUomPublicId,ct);
        if(enteredUom is null)return null;
        if(!await dbContext.Set<ProductUomRecord>().AsNoTracking().AnyAsync(
            x=>x.CompanyId==companyId&&x.ProductId==product.Id&&x.UomId==enteredUom.Id&&
               x.State==ProductMasterRecordState.Active&&(x.VariantId==null||x.VariantId==variantId),ct))
            return null;

        var baseAssignment=await dbContext.Set<ProductUomRecord>().AsNoTracking().SingleOrDefaultAsync(
            x=>x.CompanyId==companyId&&x.ProductId==product.Id&&x.VariantId==null&&
               x.Role==ProductUomRole.Base&&x.State==ProductMasterRecordState.Active,ct);
        if(baseAssignment is null)return null;

        return await dbContext.Set<InventoryValuationPoolRecord>().SingleOrDefaultAsync(
            x=>x.CompanyId==companyId&&x.ProductId==product.Id&&x.VariantId==variantId&&
               x.BaseUomId==baseAssignment.UomId&&x.CurrencyCode=="TRY",ct);
    }

    private async Task<PoolBalanceValue> PoolBalance(Guid companyId,long poolId,CancellationToken ct)
    {
        var row=await dbContext.Set<InventoryValuationEntryRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&x.PoolId==poolId)
            .GroupBy(_=>1).Select(g=>new PoolBalanceValue(g.Sum(x=>x.InventoryQuantityEffect),g.Sum(x=>x.InventoryBaseValueEffect)))
            .SingleOrDefaultAsync(ct);
        return row??new PoolBalanceValue(0m,0m);
    }

    private async Task<PartyRecord?> ResolveParty(Guid companyId,Guid publicId,PartyRoleType role,CancellationToken ct)
    {
        var party=await dbContext.Set<PartyRecord>().AsNoTracking()
            .SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==publicId&&x.State==PartyState.Active,ct);
        if(party is null)return null;
        return await dbContext.Set<PartyRoleRecord>().AsNoTracking()
            .AnyAsync(x=>x.PartyId==party.Id&&x.RoleType==role&&x.State==PartyRoleState.Active,ct)?party:null;
    }

    private async Task<MoneyAccount?> ResolveMoneyAccount(Guid companyId,FinanceMoneyAccountKind kind,Guid publicId,CancellationToken ct)
    {
        if(kind==FinanceMoneyAccountKind.Cash){
            var a=await dbContext.Set<CashAccountRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==publicId&&x.State==FinanceAccountState.Active,ct);
            return a is null?null:new MoneyAccount(a.Id,a.PublicId,a.CurrencyCode);
        }
        var b=await dbContext.Set<BankAccountRecord>().AsNoTracking().SingleOrDefaultAsync(x=>x.CompanyId==companyId&&x.PublicId==publicId&&x.State==FinanceAccountState.Active,ct);
        return b is null?null:new MoneyAccount(b.Id,b.PublicId,b.CurrencyCode);
    }

    private async Task<decimal> MoneyBalance(Guid companyId,FinanceMoneyAccountKind kind,long id,CancellationToken ct) =>
        kind==FinanceMoneyAccountKind.Cash
            ? await dbContext.Set<CashLedgerEntryRecord>().AsNoTracking().Where(x=>x.CompanyId==companyId&&x.CashAccountId==id)
                .SumAsync(x=>(decimal?)(x.Direction==FinanceMoneyDirection.In?x.Amount:-x.Amount),ct)??0m
            : await dbContext.Set<BankLedgerEntryRecord>().AsNoTracking().Where(x=>x.CompanyId==companyId&&x.BankAccountId==id)
                .SumAsync(x=>(decimal?)(x.Direction==FinanceMoneyDirection.In?x.Amount:-x.Amount),ct)??0m;

    private async Task<decimal> PartyBalance(Guid companyId,long partyId,FinancePartyRole role,string currency,CancellationToken ct) =>
        await dbContext.Set<AccountLedgerEntryRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&x.PartyId==partyId&&x.PartyRole==role&&x.CurrencyCode==currency)
            .SumAsync(x=>(decimal?)(role==FinancePartyRole.Customer
                ? (x.Direction==FinanceLedgerDirection.Debit?x.Amount:-x.Amount)
                : (x.Direction==FinanceLedgerDirection.Credit?x.Amount:-x.Amount)),ct)??0m;

    private async Task<ApplicationError?> GatePeriod(Guid companyId,DateOnly postingDate,CancellationToken ct)
    {
        var periods=await dbContext.Set<FinancePostingPeriodRecord>().AsNoTracking()
            .Where(x=>x.CompanyId==companyId&&x.StartDate<=postingDate&&x.EndDate>=postingDate).ToArrayAsync(ct);
        if(periods.Length==0)return new ApplicationError(ErrorCategory.BusinessRule,"finance.period.missing","No Finance Posting Period covers the posting date.");
        if(periods.Length>1)return new ApplicationError(ErrorCategory.Conflict,"finance.period.overlap","Multiple Finance Posting Periods cover the posting date.");
        return periods[0].State switch{
            FinancePostingPeriodState.Open=>null,
            FinancePostingPeriodState.Frozen=>new ApplicationError(ErrorCategory.BusinessRule,"finance.period.frozen","Posting date belongs to a FROZEN period; explicit override approval path is required."),
            _=>new ApplicationError(ErrorCategory.BusinessRule,"finance.period.closed","Posting date belongs to a CLOSED period.")
        };
    }

    private async Task<bool> SourceTransactionExists(Guid companyId,FinanceTransactionKind kind,Guid sourceId,CancellationToken ct) =>
        await dbContext.Set<FinanceTransactionRecord>().AsNoTracking()
            .AnyAsync(x=>x.CompanyId==companyId&&x.Kind==kind&&x.SourceDocumentPublicId==sourceId&&x.ReversalOfTransactionId==null,ct);

    private static FinanceTransactionRecord NewTransaction(
        IExecutionContext c,FinanceTransactionKind kind,decimal amount,DateOnly documentDate,DateOnly postingDate,
        long? partyId,FinancePartyRole? role,string sourceModule,string sourceEntity,Guid? sourceDocument,string? reason)
    {
        var a=FinanceAmount.RoundValue(amount);
        return new FinanceTransactionRecord{PublicId=Guid.NewGuid(),CompanyId=c.CompanyId,Kind=kind,State=FinanceTransactionState.Posted,
            CurrencyCode="TRY",BaseCurrencyCode="TRY",Amount=a,BaseAmount=a,PartyId=partyId,PartyRole=role,
            DocumentDate=documentDate,PostingDate=postingDate,SourceModule=sourceModule,SourceEntityType=sourceEntity,
            SourceDocumentPublicId=sourceDocument,Reason=Normalize(reason),CreatorActorId=c.ActorId,PostedAt=DateTimeOffset.UtcNow,Version=1};
    }

    private void AddAccountEntry(long txId,Guid companyId,long partyId,FinancePartyRole role,FinanceLedgerDirection direction,
        decimal amount,DateOnly? dueDate,DateTimeOffset now) =>
        dbContext.Add(new AccountLedgerEntryRecord{PublicId=Guid.NewGuid(),TransactionId=txId,CompanyId=companyId,PartyId=partyId,PartyRole=role,
            Direction=direction,CurrencyCode="TRY",Amount=amount,BaseAmount=amount,DueDate=dueDate,PostedAt=now});

    private void AddMoneyEntry(FinanceMoneyAccountKind kind,long accountId,long txId,Guid companyId,FinanceMoneyDirection direction,decimal amount,DateTimeOffset now)
    {
        if(kind==FinanceMoneyAccountKind.Cash)
            dbContext.Add(new CashLedgerEntryRecord{PublicId=Guid.NewGuid(),TransactionId=txId,CompanyId=companyId,CashAccountId=accountId,
                Direction=direction,CurrencyCode="TRY",Amount=amount,BaseAmount=amount,PostedAt=now});
        else
            dbContext.Add(new BankLedgerEntryRecord{PublicId=Guid.NewGuid(),TransactionId=txId,CompanyId=companyId,BankAccountId=accountId,
                Direction=direction,CurrencyCode="TRY",Amount=amount,BaseAmount=amount,PostedAt=now});
    }

    private async Task<Result<FinanceMutationReceipt>> Mutate(
        string scope,string operationKey,string action,string entityType,IExecutionContext c,
        Func<CancellationToken,Task<Result<FinanceMutationReceipt>>> mutation,CancellationToken ct)
    {
        if(string.IsNullOrWhiteSpace(operationKey))
            return Validation("finance.operation_key.required","Idempotency operation key is required.");
        var owns=dbContext.Database.CurrentTransaction is null;
        IDbContextTransaction? tx=null;
        if(owns)tx=await dbContext.Database.BeginTransactionAsync(ct);
        try{
            var result=await mutation(ct);
            if(result.IsFailure){
                if(tx is not null)await tx.RollbackAsync(ct);
                dbContext.ChangeTracker.Clear();return result;
            }
            var now=DateTimeOffset.UtcNow;
            idempotencyStore.Add(new IdempotencyOperation(scope,operationKey,null,now));
            auditWriter.Append(new AuditEntry(c.ActorId,c.CompanyId,c.BranchId,c.CorrelationId.Value,"Finance",action,entityType,result.Value!.PublicId,null,now));
            outboxWriter.Enqueue(new OutboxMessage(Guid.NewGuid(),$"Finance.{action}","Finance",result.Value.PublicId,1,
                JsonSerializer.Serialize(new{entityType,entityPublicId=result.Value.PublicId,state=result.Value.State,version=result.Value.Version}),now,now));
            await dbContext.SaveChangesAsync(ct);
            if(!await idempotencyStore.MarkSucceededAsync(scope,operationKey,"finance.completed",now,ct))
                throw new InvalidOperationException("Finance idempotency state could not be completed.");
            if(tx is not null)await tx.CommitAsync(ct);
            return result;
        }catch(DbUpdateConcurrencyException){
            if(tx is not null)await tx.RollbackAsync(ct);dbContext.ChangeTracker.Clear();
            return Conflict("finance.concurrency.stale","Finance state was changed by another operation.");
        }catch(DbUpdateException ex)when(IsConstraint(ex,"ux_idempotency_scope_key")){
            if(tx is not null)await tx.RollbackAsync(ct);dbContext.ChangeTracker.Clear();
            return Conflict("finance.operation.duplicate","The operation key was already used.");
        }catch(DbUpdateException ex)when(ex.InnerException is PostgresException pg&&pg.SqlState==PostgresErrorCodes.UniqueViolation){
            if(tx is not null)await tx.RollbackAsync(ct);dbContext.ChangeTracker.Clear();
            return Conflict("finance.unique.conflict","A Finance identity conflicts with an existing record.");
        }finally{if(tx is not null)await tx.DisposeAsync();}
    }

    private static bool IsConstraint(DbUpdateException ex,string name) =>
        ex.InnerException is PostgresException pg&&pg.SqlState==PostgresErrorCodes.UniqueViolation&&string.Equals(pg.ConstraintName,name,StringComparison.Ordinal);

    private static ApplicationError? ValidatePosting(decimal amount,string currency,DateOnly postingDate)
    {
        if(amount<=0m)return new ApplicationError(ErrorCategory.Validation,"finance.amount.invalid","Amount must be positive.");
        if(!ValidTry(currency))return new ApplicationError(ErrorCategory.BusinessRule,"finance.currency.unsupported","Authoritative Finance posting is TRY-only until company base-currency/FX authority exists.");
        if(postingDate==default)return new ApplicationError(ErrorCategory.Validation,"finance.posting_date.required","Posting date is required.");
        return null;
    }

    private static bool ValidTry(string value)=>string.Equals(value?.Trim(),"TRY",StringComparison.OrdinalIgnoreCase);
    private static string? Normalize(string? value)=>string.IsNullOrWhiteSpace(value)?null:value.Trim();
    private static string? MaskIban(string? value)
    {
        var s=Normalize(value);if(s is null)return null;
        return s.Length<=8?new string('*',s.Length):s[..4]+new string('*',Math.Max(4,s.Length-8))+s[^4..];
    }
    private static string Fingerprint(Guid bankId,ImportStatementLineInput line)
    {
        var canonical=$"{bankId:D}|{line.BookingDate:yyyy-MM-dd}|{line.ValueDate:yyyy-MM-dd}|{FinanceAmount.RoundTry(line.Amount)}|TRY|{Normalize(line.Description)}";
        return Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(canonical))).ToLowerInvariant();
    }
    private static FinanceLedgerDirection Opposite(FinanceLedgerDirection x)=>x==FinanceLedgerDirection.Debit?FinanceLedgerDirection.Credit:FinanceLedgerDirection.Debit;
    private static FinanceMoneyDirection Opposite(FinanceMoneyDirection x)=>x==FinanceMoneyDirection.In?FinanceMoneyDirection.Out:FinanceMoneyDirection.In;
    private static Result<FinanceMutationReceipt> Success(Guid id,string state,long version,IExecutionContext c)=>Result<FinanceMutationReceipt>.Success(new(id,state,version,c.CorrelationId.Value));
    private static Result<FinanceMutationReceipt> Validation(string code,string message)=>Result<FinanceMutationReceipt>.Failure(new ApplicationError(ErrorCategory.Validation,code,message));
    private static Result<FinanceMutationReceipt> Business(string code,string message)=>Result<FinanceMutationReceipt>.Failure(new ApplicationError(ErrorCategory.BusinessRule,code,message));
    private static Result<FinanceMutationReceipt> NotFound(string code,string message)=>Result<FinanceMutationReceipt>.Failure(new ApplicationError(ErrorCategory.NotFound,code,message));
    private static Result<FinanceMutationReceipt> Conflict(string code,string message)=>Result<FinanceMutationReceipt>.Failure(new ApplicationError(ErrorCategory.Conflict,code,message));
    private static Result<T> Fail<T>(string code,string message)=>Result<T>.Failure(new ApplicationError(ErrorCategory.BusinessRule,code,message));

    private async Task<Dictionary<long,ProductRecord>> MapProducts(Guid companyId,IEnumerable<long> ids,CancellationToken ct)
    {
        var a=ids.Distinct().ToArray();return a.Length==0?new():await dbContext.Set<ProductRecord>().AsNoTracking().Where(x=>x.CompanyId==companyId&&a.Contains(x.Id)).ToDictionaryAsync(x=>x.Id,ct);
    }
    private async Task<Dictionary<long,ProductVariantRecord>> MapVariants(Guid companyId,IEnumerable<long> ids,CancellationToken ct)
    {
        var a=ids.Distinct().ToArray();return a.Length==0?new():await dbContext.Set<ProductVariantRecord>().AsNoTracking().Where(x=>x.CompanyId==companyId&&a.Contains(x.Id)).ToDictionaryAsync(x=>x.Id,ct);
    }
    private async Task<Dictionary<long,UnitOfMeasureRecord>> MapUoms(Guid companyId,IEnumerable<long> ids,CancellationToken ct)
    {
        var a=ids.Distinct().ToArray();return a.Length==0?new():await dbContext.Set<UnitOfMeasureRecord>().AsNoTracking().Where(x=>x.CompanyId==companyId&&a.Contains(x.Id)).ToDictionaryAsync(x=>x.Id,ct);
    }

    private sealed record MoneyAccount(long Id,Guid PublicId,string Currency);
    private sealed record PoolBalanceValue(decimal Quantity,decimal Value);
    private sealed record PoolEffect(long PoolId,decimal Quantity,decimal Value);
}
