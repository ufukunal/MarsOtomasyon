namespace Mars.Application.Finance;

public static class FinancePermissions
{
    public const string AccountRead = "finance.account.read";
    public const string StatementRead = "finance.statement.read";
    public const string RiskRead = "finance.risk.read";
    public const string RiskManage = "finance.risk.manage";
    public const string HoldManage = "finance.hold.manage";
    public const string RoleNettingCreate = "finance.role_netting.create";
    public const string RoleNettingApprove = "finance.role_netting.approve";
    public const string RoleNettingReverse = "finance.role_netting.reverse";

    public const string CollectionRead = "finance.collection.read";
    public const string CollectionCreate = "finance.collection.create";
    public const string CollectionEditDraft = "finance.collection.edit_draft";
    public const string CollectionPost = "finance.collection.post";
    public const string CollectionReverse = "finance.collection.reverse";
    public const string CollectionAdvance = "finance.collection.advance";

    public const string PaymentRead = "finance.payment.read";
    public const string PaymentCreate = "finance.payment.create";
    public const string PaymentEditDraft = "finance.payment.edit_draft";
    public const string PaymentSubmitApproval = "finance.payment.submit_approval";
    public const string PaymentApprove = "finance.payment.approve";
    public const string PaymentPost = "finance.payment.post";
    public const string PaymentReverse = "finance.payment.reverse";
    public const string PaymentAdvance = "finance.payment.advance";

    public const string RefundRead = "finance.refund.read";
    public const string RefundCreate = "finance.refund.create";
    public const string RefundApprove = "finance.refund.approve";
    public const string RefundPost = "finance.refund.post";
    public const string RefundReverse = "finance.refund.reverse";

    public const string CashRead = "finance.cash.read";
    public const string CashManageAccount = "finance.cash.manage_account";
    public const string CashPost = "finance.cash.post";
    public const string CashReverse = "finance.cash.reverse";
    public const string CashCount = "finance.cash.count";
    public const string CashCountReview = "finance.cash.count_review";
    public const string CashCountApprove = "finance.cash.count_approve";
    public const string CashAdjust = "finance.cash.adjust";

    public const string BankRead = "finance.bank.read";
    public const string BankManageAccount = "finance.bank.manage_account";
    public const string BankPost = "finance.bank.post";
    public const string BankReverse = "finance.bank.reverse";
    public const string BankStatementImport = "finance.bank.statement_import";
    public const string BankReconcile = "finance.bank.reconcile";
    public const string BankReconcileException = "finance.bank.reconcile_exception";

    public const string TransferCreate = "finance.transfer.create";
    public const string TransferApprove = "finance.transfer.approve";
    public const string TransferPost = "finance.transfer.post";
    public const string TransferReverse = "finance.transfer.reverse";
    public const string FxOverride = "finance.fx.override";
    public const string FxRevalue = "finance.fx.revalue";
    public const string FxRevalueApprove = "finance.fx.revalue_approve";

    public const string CostRead = "finance.cost.read";
    public const string CostLateAdjust = "finance.cost.late_adjust";
    public const string CostManualValuation = "finance.cost.manual_valuation";
    public const string CostManualValuationApprove = "finance.cost.manual_valuation_approve";
    public const string CostReconcile = "finance.cost.reconcile";
    public const string CostReverse = "finance.cost.reverse";

    public const string PeriodRead = "finance.period.read";
    public const string PeriodFreeze = "finance.period.freeze";
    public const string PeriodClose = "finance.period.close";
    public const string PeriodOverride = "finance.period.override";
    public const string PeriodReopen = "finance.period.reopen";

    public const string Export = "finance.export";

    public static readonly string[] All =
    [
        AccountRead, StatementRead, RiskRead, RiskManage, HoldManage,
        RoleNettingCreate, RoleNettingApprove, RoleNettingReverse,
        CollectionRead, CollectionCreate, CollectionEditDraft, CollectionPost, CollectionReverse, CollectionAdvance,
        PaymentRead, PaymentCreate, PaymentEditDraft, PaymentSubmitApproval, PaymentApprove, PaymentPost, PaymentReverse, PaymentAdvance,
        RefundRead, RefundCreate, RefundApprove, RefundPost, RefundReverse,
        CashRead, CashManageAccount, CashPost, CashReverse, CashCount, CashCountReview, CashCountApprove, CashAdjust,
        BankRead, BankManageAccount, BankPost, BankReverse, BankStatementImport, BankReconcile, BankReconcileException,
        TransferCreate, TransferApprove, TransferPost, TransferReverse, FxOverride, FxRevalue, FxRevalueApprove,
        CostRead, CostLateAdjust, CostManualValuation, CostManualValuationApprove, CostReconcile, CostReverse,
        PeriodRead, PeriodFreeze, PeriodClose, PeriodOverride, PeriodReopen, Export
    ];
}
