using Mars.Domain.Finance;

namespace Mars.Infrastructure.Persistence.Finance;

internal sealed class FinanceTransactionRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public FinanceTransactionKind Kind { get; set; }
    public FinanceTransactionState State { get; set; }
    public string CurrencyCode { get; set; } = string.Empty;
    public string BaseCurrencyCode { get; set; } = string.Empty;
    public decimal Amount { get; set; }
    public decimal BaseAmount { get; set; }
    public long? PartyId { get; set; }
    public FinancePartyRole? PartyRole { get; set; }
    public DateOnly DocumentDate { get; set; }
    public DateOnly PostingDate { get; set; }
    public string? SourceModule { get; set; }
    public string? SourceEntityType { get; set; }
    public Guid? SourceDocumentPublicId { get; set; }
    public long? ReversalOfTransactionId { get; set; }
    public string? Reason { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset PostedAt { get; set; }
    public DateTimeOffset? ReversedAt { get; set; }
    public long Version { get; set; }
}

internal sealed class AccountLedgerEntryRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long TransactionId { get; set; }
    public Guid CompanyId { get; set; }
    public long PartyId { get; set; }
    public FinancePartyRole PartyRole { get; set; }
    public FinanceLedgerDirection Direction { get; set; }
    public string CurrencyCode { get; set; } = string.Empty;
    public decimal Amount { get; set; }
    public decimal BaseAmount { get; set; }
    public DateOnly? DueDate { get; set; }
    public DateTimeOffset PostedAt { get; set; }
}

internal sealed class CashAccountRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public Guid? BranchId { get; set; }
    public string Code { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public string CurrencyCode { get; set; } = string.Empty;
    public FinanceAccountState State { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class CashLedgerEntryRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long TransactionId { get; set; }
    public Guid CompanyId { get; set; }
    public long CashAccountId { get; set; }
    public FinanceMoneyDirection Direction { get; set; }
    public string CurrencyCode { get; set; } = string.Empty;
    public decimal Amount { get; set; }
    public decimal BaseAmount { get; set; }
    public DateTimeOffset PostedAt { get; set; }
}

internal sealed class BankAccountRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public Guid? BranchId { get; set; }
    public string Code { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public string CurrencyCode { get; set; } = string.Empty;
    public string? Iban { get; set; }
    public FinanceAccountState State { get; set; }
    public long Version { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class BankLedgerEntryRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long TransactionId { get; set; }
    public Guid CompanyId { get; set; }
    public long BankAccountId { get; set; }
    public FinanceMoneyDirection Direction { get; set; }
    public string CurrencyCode { get; set; } = string.Empty;
    public decimal Amount { get; set; }
    public decimal BaseAmount { get; set; }
    public DateTimeOffset PostedAt { get; set; }
}

internal sealed class FinancePostingPeriodRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public DateOnly StartDate { get; set; }
    public DateOnly EndDate { get; set; }
    public FinancePostingPeriodState State { get; set; }
    public long Version { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? ChangedAt { get; set; }
}

internal sealed class InventoryValuationPoolRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public long ProductId { get; set; }
    public long? VariantId { get; set; }
    public long BaseUomId { get; set; }
    public string CurrencyCode { get; set; } = string.Empty;
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class InventoryValuationEntryRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long TransactionId { get; set; }
    public Guid CompanyId { get; set; }
    public long PoolId { get; set; }
    public FinanceValuationKind Kind { get; set; }
    public decimal InventoryQuantityEffect { get; set; }
    public decimal InventoryBaseValueEffect { get; set; }
    public decimal ExpenseBaseValueEffect { get; set; }
    public decimal? UnitBaseValue { get; set; }
    public Guid? InventoryMovementPublicId { get; set; }
    public string SourceModule { get; set; } = string.Empty;
    public string SourceEntityType { get; set; } = string.Empty;
    public Guid SourceDocumentPublicId { get; set; }
    public Guid? SourceLinePublicId { get; set; }
    public long? ReversalOfValuationEntryId { get; set; }
    public DateTimeOffset PostedAt { get; set; }
}

internal sealed class DispatchCostBridgeEntryRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long TransactionId { get; set; }
    public Guid CompanyId { get; set; }
    public long PoolId { get; set; }
    public Guid DispatchPublicId { get; set; }
    public Guid DispatchLinePublicId { get; set; }
    public Guid PhysicalSourcePublicId { get; set; }
    public Guid InventoryMovementPublicId { get; set; }
    public decimal BaseQuantity { get; set; }
    public decimal BaseValue { get; set; }
    public long? ReversalOfBridgeEntryId { get; set; }
    public DateTimeOffset PostedAt { get; set; }
}

internal sealed class DispatchCostBridgeConsumptionRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long TransactionId { get; set; }
    public Guid CompanyId { get; set; }
    public long BridgeEntryId { get; set; }
    public Guid SalesInvoicePublicId { get; set; }
    public Guid SalesInvoiceLinePublicId { get; set; }
    public decimal BaseQuantity { get; set; }
    public decimal BaseValue { get; set; }
    public long? ReversalOfConsumptionId { get; set; }
    public DateTimeOffset PostedAt { get; set; }
}

internal sealed class StatementImportBatchRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public long BankAccountId { get; set; }
    public string SourceName { get; set; } = string.Empty;
    public string? SourceReference { get; set; }
    public Guid CreatorActorId { get; set; }
    public DateTimeOffset ImportedAt { get; set; }
}

internal sealed class StatementLineRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public long BatchId { get; set; }
    public Guid CompanyId { get; set; }
    public long BankAccountId { get; set; }
    public DateOnly BookingDate { get; set; }
    public DateOnly? ValueDate { get; set; }
    public decimal Amount { get; set; }
    public string CurrencyCode { get; set; } = string.Empty;
    public string? ExternalId { get; set; }
    public string Fingerprint { get; set; } = string.Empty;
    public string? Description { get; set; }
    public FinanceStatementLineState State { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
}

internal sealed class ReconciliationMatchRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public long StatementLineId { get; set; }
    public long BankLedgerEntryId { get; set; }
    public decimal MatchedAmount { get; set; }
    public FinanceReconciliationState State { get; set; }
    public Guid ActorId { get; set; }
    public DateTimeOffset CreatedAt { get; set; }
    public DateTimeOffset? ReversedAt { get; set; }
}

internal sealed class CustomerRiskControlRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public long PartyId { get; set; }
    public decimal? CreditLimit { get; set; }
    public bool ManualHold { get; set; }
    public string? HoldReason { get; set; }
    public long Version { get; set; }
    public Guid UpdatedByActorId { get; set; }
    public DateTimeOffset UpdatedAt { get; set; }
}

internal sealed class CashCountRecord
{
    public long Id { get; set; }
    public Guid PublicId { get; set; }
    public Guid CompanyId { get; set; }
    public long CashAccountId { get; set; }
    public decimal BookBalanceSnapshot { get; set; }
    public decimal CountedBalance { get; set; }
    public decimal Difference { get; set; }
    public Guid CounterActorId { get; set; }
    public Guid? ApprovalDecisionPublicId { get; set; }
    public long? AdjustmentTransactionId { get; set; }
    public DateTimeOffset CountedAt { get; set; }
}
