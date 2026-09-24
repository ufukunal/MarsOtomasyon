namespace Mars.Application.Sales;

public static class SalesPermissions
{
    public const string QuoteRead = "sales.quote.read";
    public const string QuoteCreate = "sales.quote.create";
    public const string QuoteEditDraft = "sales.quote.edit_draft";
    public const string QuoteRevise = "sales.quote.revise";
    public const string QuoteSubmitApproval = "sales.quote.submit_approval";
    public const string QuoteApprove = "sales.quote.approve";
    public const string QuoteSendCustomer = "sales.quote.send_customer";
    public const string QuoteConvertOrder = "sales.quote.convert_order";
    public const string QuoteCancel = "sales.quote.cancel";

    public const string OrderRead = "sales.order.read";
    public const string OrderCreate = "sales.order.create";
    public const string OrderEditDraft = "sales.order.edit_draft";
    public const string OrderSubmitApproval = "sales.order.submit_approval";
    public const string OrderApprove = "sales.order.approve";
    public const string OrderConfirm = "sales.order.confirm";
    public const string OrderAmend = "sales.order.amend";
    public const string OrderActivateAmendment = "sales.order.activate_amendment";
    public const string OrderHold = "sales.order.hold";
    public const string OrderReleaseHold = "sales.order.release_hold";
    public const string OrderCancelRemaining = "sales.order.cancel_remaining";
    public const string OrderClose = "sales.order.close";

    public const string DispatchRead = "sales.dispatch.read";
    public const string DispatchCreate = "sales.dispatch.create";
    public const string DispatchEditDraft = "sales.dispatch.edit_draft";
    public const string DispatchPost = "sales.dispatch.post";
    public const string DispatchHandoff = "sales.dispatch.handoff";
    public const string DispatchReverse = "sales.dispatch.reverse";

    public const string InvoiceRead = "sales.invoice.read";
    public const string InvoiceCreate = "sales.invoice.create";
    public const string InvoiceEditDraft = "sales.invoice.edit_draft";

    public const string ProformaRead = "sales.proforma.read";
    public const string ProformaCreate = "sales.proforma.create";
    public const string ProformaCancel = "sales.proforma.cancel";
    public const string ProformaExport = "sales.proforma.export";

    public static IReadOnlyList<string> All { get; } =
    [
        QuoteRead, QuoteCreate, QuoteEditDraft, QuoteRevise,
        QuoteSubmitApproval, QuoteApprove, QuoteSendCustomer,
        QuoteConvertOrder, QuoteCancel,
        OrderRead, OrderCreate, OrderEditDraft, OrderSubmitApproval,
        OrderApprove, OrderConfirm, OrderAmend, OrderActivateAmendment,
        OrderHold, OrderReleaseHold, OrderCancelRemaining, OrderClose,
        DispatchRead, DispatchCreate, DispatchEditDraft, DispatchPost,
        DispatchHandoff, DispatchReverse,
        InvoiceRead, InvoiceCreate, InvoiceEditDraft,
        ProformaRead, ProformaCreate, ProformaCancel, ProformaExport
    ];
}
