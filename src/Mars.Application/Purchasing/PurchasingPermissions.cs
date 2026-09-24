namespace Mars.Application.Purchasing;

public static class PurchasingPermissions
{
    public const string OrderRead = "purchasing.order.read";
    public const string OrderCreate = "purchasing.order.create";
    public const string OrderEditDraft = "purchasing.order.edit_draft";
    public const string OrderSubmitApproval = "purchasing.order.submit_approval";
    public const string OrderApprove = "purchasing.order.approve";
    public const string OrderConfirm = "purchasing.order.confirm";
    public const string OrderAmend = "purchasing.order.amend";
    public const string OrderCancelRemaining = "purchasing.order.cancel_remaining";
    public const string OrderClose = "purchasing.order.close";
    public const string ReceiptRead = "purchasing.receipt.read";
    public const string ReceiptCreate = "purchasing.receipt.create";
    public const string ReceiptEditDraft = "purchasing.receipt.edit_draft";
    public const string ReceiptPost = "purchasing.receipt.post";
    public const string ReceiptReverse = "purchasing.receipt.reverse";
    public const string MatchRead = "purchasing.match.read";
    public const string MatchApproveException = "purchasing.match.approve_exception";
    public const string InvoiceRead = "purchasing.invoice.read";
    public const string InvoiceCreate = "purchasing.invoice.create";
    public const string InvoiceEditDraft = "purchasing.invoice.edit_draft";
    public const string Export = "purchasing.export";

    public static IReadOnlyList<string> All { get; } =
    [
        OrderRead, OrderCreate, OrderEditDraft, OrderSubmitApproval, OrderApprove,
        OrderConfirm, OrderAmend, OrderCancelRemaining, OrderClose,
        ReceiptRead, ReceiptCreate, ReceiptEditDraft, ReceiptPost, ReceiptReverse,
        MatchRead, MatchApproveException,
        InvoiceRead, InvoiceCreate, InvoiceEditDraft, Export
    ];
}
