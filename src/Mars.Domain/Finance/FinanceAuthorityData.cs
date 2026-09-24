namespace Mars.Domain.Finance;

public enum FinanceTransactionKind
{
    OpeningBalance = 1,
    Collection = 2,
    SupplierPayment = 3,
    CustomerRefund = 4,
    SupplierRefund = 5,
    RoleNetting = 6,
    TreasuryTransfer = 7,
    CashCountAdjustment = 8,
    SalesInvoice = 9,
    SupplierInvoice = 10,
    GoodsReceiptProvisional = 11,
    DispatchCarryingValue = 12,
    SalesInvoiceCogs = 13,
    SupplierInvoiceLateCost = 14,
    StockCountAdjustment = 15,
    ScrapWriteOff = 16,
    Reversal = 17
}

public enum FinanceTransactionState
{
    Posted = 1,
    Reversed = 2
}

public enum FinancePartyRole
{
    Customer = 1,
    Supplier = 2
}

public enum FinanceLedgerDirection
{
    Debit = 1,
    Credit = 2
}

public enum FinanceMoneyDirection
{
    In = 1,
    Out = 2
}

public enum FinanceMoneyAccountKind
{
    Cash = 1,
    Bank = 2
}

public enum FinanceAccountState
{
    Active = 1,
    Inactive = 2
}

public enum FinancePostingPeriodState
{
    Open = 1,
    Frozen = 2,
    Closed = 3
}

public enum FinanceValuationKind
{
    GoodsReceiptProvisional = 1,
    DispatchCarryingValue = 2,
    SalesInvoiceCogs = 3,
    SupplierInvoiceLateCost = 4,
    StockCountAdjustment = 5,
    ScrapWriteOff = 6,
    Reversal = 7
}

public enum FinanceStatementLineState
{
    Unmatched = 1,
    PartiallyMatched = 2,
    Matched = 3,
    Ignored = 4
}

public enum FinanceReconciliationState
{
    Active = 1,
    Reversed = 2
}

public static class FinanceAmount
{
    public const string CurrentBaseCurrency = "TRY";

    public static decimal RoundTry(decimal amount) =>
        Math.Round(amount, 2, MidpointRounding.AwayFromZero);

    public static decimal RoundValue(decimal amount) =>
        Math.Round(amount, 9, MidpointRounding.AwayFromZero);
}
